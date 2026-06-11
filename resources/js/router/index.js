import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Lazy-load all pages
const HomePage           = () => import('@/pages/HomePage.vue');
const NewsPage           = () => import('@/pages/NewsPage.vue');
const NewsDetailPage     = () => import('@/pages/NewsDetailPage.vue');
const EventDetailPage    = () => import('@/pages/EventDetailPage.vue');
const RoutesPage         = () => import('@/pages/RoutesPage.vue');
const RouteDetailPage    = () => import('@/pages/RouteDetailPage.vue');
const PlacesPage         = () => import('@/pages/PlacesPage.vue');
const PlaceDetailPage    = () => import('@/pages/PlaceDetailPage.vue');
const SettingsPage       = () => import('@/pages/SettingsPage.vue');
const NotificationsPage  = () => import('@/pages/NotificationsPage.vue');
const AdminPage          = () => import('@/pages/AdminPage.vue');
const ProviderPage       = () => import('@/pages/provider/DashboardPage.vue');
const PoiEditorPage      = () => import('@/pages/provider/PoiEditorPage.vue');
const NewsEditorPage     = () => import('@/pages/admin/NewsEditorPage.vue');
const FleetPage          = () => import('@/pages/fleet/DashboardPage.vue');
const AssignmentDetailPage = () => import('@/pages/AssignmentDetailPage.vue');
const LoginPage          = () => import('@/pages/auth/LoginPage.vue');
const RegisterPage       = () => import('@/pages/auth/RegisterPage.vue');
const ForbiddenPage      = () => import('@/pages/ForbiddenPage.vue');
const NotFoundPage       = () => import('@/pages/NotFoundPage.vue');

const routes = [
    { path: '/',                    name: 'home',             component: HomePage },
    { path: '/news',                name: 'news',             component: NewsPage },
    { path: '/news/:id',            name: 'news-detail',      component: NewsDetailPage },
    { path: '/events/:id',          name: 'event-detail',     component: EventDetailPage },
    { path: '/routes',              name: 'routes',           component: RoutesPage, meta: { auth: true } },
    { path: '/routes/:id',          name: 'route-detail',     component: RouteDetailPage, meta: { auth: true } },
    { path: '/places',              name: 'places',           component: PlacesPage },
    { path: '/places/:id',          name: 'place-detail',     component: PlaceDetailPage },
    { path: '/profile',             name: 'profile',          redirect: { name: 'settings' }, meta: { auth: true } },
    { path: '/settings',            name: 'settings',         component: SettingsPage, meta: { auth: true } },
    { path: '/notifications',       name: 'notifications',    component: NotificationsPage, meta: { auth: true } },
    { path: '/assignments/:id',     name: 'assignment-detail', component: AssignmentDetailPage, meta: { auth: true } },
    { path: '/admin',               name: 'admin',            component: AdminPage, meta: { auth: true, role: 'admin' } },
    { path: '/admin/news/new',      name: 'admin-news-new',   component: NewsEditorPage, meta: { auth: true, role: 'admin' } },
    { path: '/admin/news/:id/edit', name: 'admin-news-edit',  component: NewsEditorPage, meta: { auth: true, role: 'admin' } },
    { path: '/provider',            name: 'provider',         component: ProviderPage, meta: { auth: true, role: 'provider' } },
    { path: '/provider/poi/new',    name: 'provider-poi-new', component: PoiEditorPage, meta: { auth: true, role: 'provider' } },
    { path: '/provider/poi/:id/edit', name: 'provider-poi-edit', component: PoiEditorPage, meta: { auth: true, role: 'provider' } },
    { path: '/fleet',               name: 'fleet',            component: FleetPage, meta: { auth: true, role: 'fleet' } },
    { path: '/login',               name: 'login',            component: LoginPage, meta: { guest: true } },
    { path: '/register',            name: 'register',         component: RegisterPage, meta: { guest: true } },
    { path: '/forbidden',           name: 'forbidden',        component: ForbiddenPage },
    { path: '/:pathMatch(.*)*',     name: 'not-found',        component: NotFoundPage },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        if (to.hash) return { el: to.hash, behavior: 'smooth' };
        return { top: 0 };
    },
});

// Navigation guards
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Try to restore user from token on first load
    if (auth.token && !auth.user) {
        await auth.fetchMe();
    }

    // Redirect logged-in users away from guest-only pages
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'home' };
    }

    // Redirect unauthenticated users to login
    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    // Role-based protection (admin always passes)
    if (to.meta.role && auth.user) {
        const allowed = to.meta.role;
        if (auth.user.role !== allowed && auth.user.role !== 'admin') {
            return {
                name: 'forbidden',
                query: {
                    from: to.fullPath,
                    message: 'У вашей учётной записи нет доступа к этому разделу.',
                },
            };
        }
    }
});

export default router;
