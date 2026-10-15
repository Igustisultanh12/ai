import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { useAuth } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'landing',
        component: () => import('@/views/LandingView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/views/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/projects',
        name: 'projects',
        component: () => import('@/views/ProjectsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/workspace/:id',
        name: 'workspace',
        component: () => import('@/views/WorkspaceView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/chat/:id?',
        name: 'chat',
        component: () => import('@/views/ChatView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/usage',
        name: 'usage',
        component: () => import('@/views/UsageView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: () => import('@/views/AdminDashboardView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/users',
        name: 'admin-users',
        component: () => import('@/views/AdminUsersView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/agent',
        name: 'admin-agent',
        component: () => import('@/views/AdminAgentView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/audit',
        name: 'admin-audit',
        component: () => import('@/views/AdminAuditView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const { user, isAuthenticated, isAdmin, isLoading, checkAuth } = useAuth();

    if (isLoading.value) {
        await checkAuth();
    }

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return next({ name: 'login' });
    }

    if (to.meta.guestOnly && isAuthenticated.value) {
        return next({ name: 'dashboard' });
    }

    if (to.meta.requiresAdmin && !isAdmin.value) {
        return next({ name: 'dashboard' });
    }

    next();
});
