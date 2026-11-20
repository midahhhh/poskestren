// middleware/auth.js - Middleware Autentikasi dan Role-Based Access Control (RBAC)
function requireAuth(target = 'petugas') {
    return (req, res, next) => {
        if (!req.session || !req.session.userId) {
            req.session.flashError = target === 'wali' 
                ? 'Silakan masuk ke Portal Wali Santri terlebih dahulu.' 
                : 'Akses Terbatas: Anda harus masuk (login) sebagai Petugas Medis / Admin.';
            return res.redirect(target === 'wali' ? '/login' : '/login-petugas');
        }
        next();
    };
}

function requireRole(allowedRoles = []) {
    return (req, res, next) => {
        const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles];
        const userRole = req.session ? req.session.role : null;

        if (!userRole || !roles.includes(userRole)) {
            req.session.flashError = `Akses Ditolak: Role Anda (${userRole}) tidak memiliki izin untuk halaman ini.`;
            if (userRole === 'santri_wali') {
                return res.redirect('/wali');
            } else if (userRole === 'petugas_farmasi') {
                return res.redirect('/farmasi');
            } else {
                return res.redirect('/dashboard');
            }
        }
        next();
    };
}

function redirectIfAuthenticated(req, res, next) {
    if (req.session && req.session.userId) {
        if (req.session.role === 'santri_wali') {
            return res.redirect('/wali');
        } else if (req.session.role === 'petugas_farmasi') {
            return res.redirect('/farmasi');
        } else {
            return res.redirect('/dashboard');
        }
    }
    next();
}

module.exports = {
    requireAuth,
    requireRole,
    redirectIfAuthenticated
};
