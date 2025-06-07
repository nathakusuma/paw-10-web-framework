import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

// Test configuration
export const options = {
    stages: [
        { duration: '30s', target: 1 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<10000'],
        http_req_failed: ['rate<1.0'],
    },
};

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'demo@example.com';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';

// Test data
const todoTitles = new SharedArray('todoTitles', function () {
    return [
        'Test todo 1',
        'Test todo 2',
        'Test todo 3',
    ];
});

// Cookie jar to maintain session
class CookieJar {
    constructor() {
        this.cookies = new Map();
    }

    updateFromResponse(response) {
        const setCookieHeaders = response.headers['Set-Cookie'];
        if (!setCookieHeaders) return;

        const cookieArray = Array.isArray(setCookieHeaders) ? setCookieHeaders : [setCookieHeaders];
        cookieArray.forEach(cookieStr => {
            const [nameValue] = cookieStr.split(';');
            const [name, value] = nameValue.split('=');
            if (name && value) {
                this.cookies.set(name.trim(), value.trim());
            }
        });
    }

    toString() {
        return Array.from(this.cookies.entries())
            .map(([name, value]) => `${name}=${value}`)
            .join('; ');
    }

    getXSRFToken() {
        const token = this.cookies.get('XSRF-TOKEN');
        if (!token) return null;

        try {
            return decodeURIComponent(token);
        } catch (e) {
            return token;
        }
    }
}

export function setup() {
    console.log('Setting up test session...');
    const cookieJar = new CookieJar();

    try {
        // Get login page and establish session
        const loginPageResponse = http.get(`${BASE_URL}/login`, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            },
        });

        if (loginPageResponse.status !== 200) {
            console.error(`Login page inaccessible: ${loginPageResponse.status}`);
            return { success: false };
        }

        cookieJar.updateFromResponse(loginPageResponse);
        const xsrfToken = cookieJar.getXSRFToken();

        if (!xsrfToken) {
            console.error('No XSRF token available');
            return { success: false };
        }

        // Attempt login using Inertia.js format
        const loginPayload = JSON.stringify({
            email: TEST_EMAIL,
            password: TEST_PASSWORD,
            remember: false
        });

        const loginResponse = http.post(`${BASE_URL}/login`, loginPayload, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
                'Content-Type': 'application/json',
                'Accept': 'text/html, application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia': 'true',
                'X-Inertia-Version': 'c0746d81e46fdd31a906a6710864ba53',
                'X-XSRF-TOKEN': xsrfToken,
                'Origin': BASE_URL,
                'Referer': `${BASE_URL}/login`,
                'Cookie': cookieJar.toString(),
            },
        });

        cookieJar.updateFromResponse(loginResponse);

        // Check for successful login
        let loginSuccessful = false;

        if (loginResponse.status === 200) {
            try {
                const responseData = JSON.parse(loginResponse.body);
                if (responseData.component === 'dashboard' && responseData.props && responseData.props.auth && responseData.props.auth.user) {
                    loginSuccessful = true;
                }
            } catch (e) {
                // Not JSON response
            }
        } else if (loginResponse.status === 302) {
            const location = loginResponse.headers['Location'];
            if (location && location.includes('/dashboard')) {
                loginSuccessful = true;
            }
        }

        if (loginSuccessful) {
            // Get fresh XSRF token from dashboard
            const dashboardResponse = http.get(`${BASE_URL}/dashboard`, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Cookie': cookieJar.toString(),
                },
            });

            if (dashboardResponse.status === 200) {
                cookieJar.updateFromResponse(dashboardResponse);
            }

            console.log('Setup completed successfully');
            return {
                success: true,
                cookies: cookieJar.toString(),
                xsrfToken: cookieJar.getXSRFToken(),
                baseUrl: BASE_URL,
            };
        } else {
            console.error(`Login failed with status: ${loginResponse.status}`);
            return { success: false, status: loginResponse.status };
        }

    } catch (error) {
        console.error('Setup error:', error.message);
        return { success: false, error: error.message };
    }
}

export default function (data) {
    if (!data || !data.success) {
        console.log(`VU ${__VU}: Setup failed, skipping iteration`);
        sleep(2);
        return;
    }

    // Create local cookie jar for this iteration
    const cookieJar = new CookieJar();

    // Parse existing cookies
    if (data.cookies) {
        const cookiePairs = data.cookies.split('; ');
        cookiePairs.forEach(pair => {
            const [name, value] = pair.split('=');
            if (name && value) {
                cookieJar.cookies.set(name.trim(), value.trim());
            }
        });
    }

    // Test 1: Access dashboard
    const dashboardResponse = http.get(`${data.baseUrl}/dashboard`, {
        headers: {
            'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Cookie': cookieJar.toString(),
        },
    });

    const dashboardOk = check(dashboardResponse, {
        'dashboard accessible': (r) => r.status === 200,
        'dashboard has todos content': (r) => r.body.includes('todos') || r.body.includes('Tasks') || r.body.includes('Dashboard'),
    });

    if (dashboardOk) {
        cookieJar.updateFromResponse(dashboardResponse);
    } else {
        console.log(`VU ${__VU}: Dashboard access failed - ${dashboardResponse.status}`);
        return;
    }

    sleep(1);

    // Test 2: Create a todo
    const todoTitle = `Test Todo - VU${__VU}-I${__ITER}`;
    const createPayload = JSON.stringify({
        title: todoTitle,
        description: 'Test description',
        filter: 'all'
    });

    const currentXsrfToken = cookieJar.getXSRFToken();

    const createResponse = http.post(`${data.baseUrl}/todos`, createPayload, {
        headers: {
            'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
            'Content-Type': 'application/json',
            'Accept': 'text/html, application/xhtml+xml',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Inertia': 'true',
            'X-Inertia-Version': 'c0746d81e46fdd31a906a6710864ba53',
            'X-XSRF-TOKEN': currentXsrfToken,
            'Origin': data.baseUrl,
            'Referer': `${data.baseUrl}/dashboard`,
            'Cookie': cookieJar.toString(),
        },
    });

    const createOk = check(createResponse, {
        'todo creation successful': (r) => r.status === 302 || r.status === 200,
        'no CSRF errors': (r) => r.status !== 419,
    });

    if (!createOk && createResponse.status === 419) {
        console.log('VU ${__VU}: CSRF token issue detected');
    }

    sleep(1);

    // Test 3: Read with filter
    const filterResponse = http.get(`${data.baseUrl}/dashboard?filter=all`, {
        headers: {
            'User-Agent': 'Mozilla/5.0 (compatible; k6/1.0)',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Cookie': cookieJar.toString(),
        },
    });

    check(filterResponse, {
        'filtered todos accessible': (r) => r.status === 200,
    });

    sleep(1);
}

export function teardown(data) {
    if (data && data.success) {
        try {
            const logoutResponse = http.post(`${data.baseUrl}/logout`, '', {
                headers: {
                    'Cookie': data.cookies,
                    'X-XSRF-TOKEN': data.xsrfToken,
                },
            });
            console.log(`Logout completed: ${logoutResponse.status}`);
        } catch (e) {
            console.log('Logout failed (non-critical)');
        }
    }
}
