import { create } from 'zustand';
import { TripSession, startTrip, endTrip, currentTrip } from '@/api/trip';
import { getRoute, RoutePlan } from '@/api/routes';

interface TripState {
    session: TripSession | null;
    activePlan: RoutePlan | null;
    isTracking: boolean;

    loadCurrent: () => Promise<void>;
    start: (routePlanId?: number, plan?: RoutePlan) => Promise<void>;
    end: () => Promise<void>;
    setTracking: (v: boolean) => void;
}

export const useTripStore = create<TripState>((set) => ({
    session: null,
    activePlan: null,
    isTracking: false,

    loadCurrent: async () => {
        try {
            const session = await currentTrip();
            let activePlan: RoutePlan | null = null;

            if (session?.route_plan_id) {
                try {
                    activePlan = await getRoute(session.route_plan_id);
                } catch {
                    activePlan = null;
                }
            }

            set({ session, activePlan });
        } catch { /* ignore */ }
    },

    start: async (routePlanId, plan) => {
        const session = await startTrip(routePlanId);
        set({ session, activePlan: plan ?? null, isTracking: true });
    },

    end: async () => {
        await endTrip();
        set({ session: null, activePlan: null, isTracking: false });
    },

    setTracking: (v) => set({ isTracking: v }),
}));
