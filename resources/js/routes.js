/**
 * Server paths the client links to, kept in one place because the application has
 * no route helper on the client. These mirror routes/web.php and routes/settings.php.
 */
export const routes = {
    home: '/',
    dashboard: '/dashboard',
    mailbox: '/emails',
    email: (id) => `/emails/${id}`,
    mailSettings: '/settings/mail',
    mailSettingsTest: '/settings/mail/test',
    mailSettingsSync: '/settings/mail/sync',
    login: '/login',
    register: '/register',
    logout: '/logout',
};
