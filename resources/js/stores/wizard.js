import { defineStore } from 'pinia';

export const useWizardStore = defineStore('wizard', {
    state: () => ({
        step: 1,

        // Step 1 — vehicle
        vehicle: null,       // full vehicle object from API
        startFuel: 0,        // litres

        // Step 2 — cargo
        hasCargo: false,
        cargo: {
            weight_t: 0,
            flag: 'Обычный',         // Обычный / Опасный / Негабарит / Рефриж
            requirements: '',
        },

        // Step 3 — route
        origin: null,        // { lat, lng, label }
        destination: null,
        waypoints: [],       // [{ lat, lng, label } | null]  — null = empty slot
        startTime: '',

        // Step 4 — preferences
        prefs: {
            fuel_network: 'Любые',
            lodging_type: 'Любой',
            include_food: false,
            reserve_percent: 15,
            planning_mode: 'Безопасный',
            continuous_drive_hours: 4,
            include_rest_stop: true,
            no_toll_roads: 'Нет',
        },

        // Step 5 — POI selection along route
        selectedPois: [],        // full POI objects selected by user
        routePoiIds: [],         // selected POI IDs that must become route transit points
        showOnlySelected: false, // eye toggle
    }),

    getters: {
        canProceed: (state) => {
            if (state.step === 1) return !!state.vehicle;
            if (state.step === 3) return !!state.origin && !!state.destination;
            return true;
        },

        apiPayload: (state) => {
            const routePoiPoints = state.selectedPois
                .filter(p => state.routePoiIds.includes(p.id))
                .map((poi) => {
                    const lat = poi.coordinates?.lat ?? poi.lat;
                    const lng = poi.coordinates?.lng ?? poi.lng;
                    if (lat == null || lng == null) return null;
                    return {
                        lat,
                        lng,
                        label: poi.name,
                        poi_id: poi.id,
                    };
                })
                .filter(Boolean);
            const via = [
                ...state.waypoints.filter(Boolean),
                ...routePoiPoints,
            ];

            const payload = {
                origin:      state.origin,
                destination: state.destination,
                via,
                start_time:  state.startTime || undefined,
                start_fuel_l: state.startFuel
                    || Math.round((state.vehicle?.tank_capacity_l ?? 600) * 0.7),
                vehicle_id:  state.vehicle?.id,
                preferences: {
                    preferred_fuel_brand:   state.prefs.fuel_network,
                    lodging_type:           state.prefs.lodging_type,
                    planning_mode:          state.prefs.planning_mode,
                    continuous_drive_hours: state.prefs.continuous_drive_hours,
                    reserve_percent:        state.prefs.reserve_percent,
                    include_rest_stop:      state.prefs.include_rest_stop,
                    no_toll_roads:          state.prefs.no_toll_roads,
                },
                selected_poi_ids: state.selectedPois.map(p => p.id),
                route_poi_ids: state.routePoiIds,
            };
            if (state.hasCargo) {
                payload.cargo = {
                    weight_t:     state.cargo.weight_t,
                    flag:         state.cargo.flag,
                    requirements: state.cargo.requirements,
                };
            }
            return payload;
        },

        routeTransitPois: (state) => state.selectedPois.filter(p => state.routePoiIds.includes(p.id)),
        optionalPois: (state) => state.selectedPois.filter(p => !state.routePoiIds.includes(p.id)),
        routeTransitLimit: (state) => Math.max(0, 8 - state.waypoints.filter(Boolean).length),
        routeTransitCount: (state) => state.routePoiIds.length,
        routeViaPoints: (state) => [
            ...state.waypoints.filter(Boolean),
            ...state.selectedPois
                .filter(p => state.routePoiIds.includes(p.id))
                .map((poi) => {
                    const lat = poi.coordinates?.lat ?? poi.lat;
                    const lng = poi.coordinates?.lng ?? poi.lng;
                    if (lat == null || lng == null) return null;
                    return {
                        lat,
                        lng,
                        label: poi.name,
                        poi_id: poi.id,
                    };
                })
                .filter(Boolean),
        ],

        /** fuel ratio for recommendation engine */
        fuelRatio: (state) => {
            const tank = state.vehicle?.tank_capacity_l ?? 600;
            return tank > 0 ? state.startFuel / tank : 1;
        },
    },

    actions: {
        next()  { if (this.step < 6) this.step++; },
        back()  { if (this.step > 1) this.step--; },
        goTo(n) { this.step = n; },

        setVehicle(v) {
            this.vehicle   = v;
            this.startFuel = Math.round((v?.tank_capacity_l ?? 600) * 0.7);
        },

        addWaypoint()        { this.waypoints.push(null); },
        removeWaypoint(idx)  { this.waypoints.splice(idx, 1); },
        setWaypoint(idx, pt) { this.waypoints[idx] = pt; },

        // Step 5 POI actions
        addPoi(poi) {
            if (!this.selectedPois.find(p => p.id === poi.id)) {
                this.selectedPois.push(poi);
                this.sortSelectedPoisByRoute();
            }
        },
        sortSelectedPoisByRoute() {
            if (!this.origin || !this.destination || this.selectedPois.length < 2) return;

            const originLat = Number(this.origin.lat);
            const originLng = Number(this.origin.lng);
            const destinationLat = Number(this.destination.lat);
            const destinationLng = Number(this.destination.lng);
            const routeLat = destinationLat - originLat;
            const routeLng = destinationLng - originLng;
            const routeLengthSquared = routeLat * routeLat + routeLng * routeLng;

            if (!Number.isFinite(routeLengthSquared) || routeLengthSquared === 0) return;

            const routePosition = (poi) => {
                const lat = Number(poi.coordinates?.lat ?? poi.lat);
                const lng = Number(poi.coordinates?.lng ?? poi.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return Number.POSITIVE_INFINITY;

                return ((lat - originLat) * routeLat + (lng - originLng) * routeLng) / routeLengthSquared;
            };

            this.selectedPois.sort((left, right) => routePosition(left) - routePosition(right));
        },
        removePoi(id) {
            this.selectedPois = this.selectedPois.filter(p => p.id !== id);
            this.routePoiIds = this.routePoiIds.filter(poiId => poiId !== id);
        },
        moveSelectedPoi(id, direction) {
            const from = this.selectedPois.findIndex(p => p.id === id);
            const to = from + direction;
            if (from < 0 || to < 0 || to >= this.selectedPois.length) {
                return false;
            }

            const [poi] = this.selectedPois.splice(from, 1);
            this.selectedPois.splice(to, 0, poi);
            return true;
        },
        isPoiSelected(id) {
            return this.selectedPois.some(p => p.id === id);
        },
        isRoutePoi(id) {
            return this.routePoiIds.includes(id);
        },
        toggleRoutePoi(id) {
            if (this.routePoiIds.includes(id)) {
                this.routePoiIds = this.routePoiIds.filter(poiId => poiId !== id);
                return true;
            }

            if (this.routePoiIds.length >= this.routeTransitLimit) {
                return false;
            }

            this.routePoiIds.push(id);
            return true;
        },
        togglePoiVisibility() {
            this.showOnlySelected = !this.showOnlySelected;
        },

        reset() { this.$reset(); },
    },
});
