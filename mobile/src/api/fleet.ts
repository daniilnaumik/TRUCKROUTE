import client from './client';

export interface Fleet {
    id: number;
    name: string;
    inn: string | null;
    description: string | null;
    owner_id?: number;
    owner?: { id: number; name: string } | null;
    is_owner?: boolean;
    drivers_count?: number;
    assignments_count?: number;
    completed_assignments_count?: number;
}

export interface FleetDriver {
    id: number;
    name: string;
    phone: string | null;
    role_in_fleet: string;
    completed_assignments_count?: number;
    rating_avg?: number | null;
    rating_count?: number;
}

export interface FleetAssignment {
    id: number;
    route_plan_id?: number | null;
    vehicle_source?: 'driver' | 'fleet';
    vehicle_id?: number | null;
    vehicle?: {
        id: number;
        title: string;
        type: string;
        model?: string | null;
    } | null;
    driver: { id: number; name: string } | null;
    origin: string;
    origin_point?: GeoPoint | null;
    destination: string;
    destination_point?: GeoPoint | null;
    via_points?: GeoPoint[] | null;
    planned_start_at: string | null;
    status: 'issued' | 'accepted' | 'in_progress' | 'completed' | 'cancelled';
    comment: string | null;
    completed_at?: string | null;
    rating_stars?: number | null;
    rating_comment?: string | null;
    rated_at?: string | null;
    fleet_id?: number;
    fleet?: Fleet | null;
}

export interface GeoPoint {
    lat: number;
    lng: number;
    label?: string;
}

export async function getFleets(): Promise<Fleet[]> {
    const { data } = await client.get('/fleets');
    return data.data ?? [];
}

export async function getFleet(id: number): Promise<Fleet> {
    const { data } = await client.get(`/fleets/${id}`);
    return data.data ?? data;
}

export async function createFleet(payload: { name: string; inn?: string; description?: string }): Promise<Fleet> {
    const { data } = await client.post('/fleets', payload);
    return data.data ?? data;
}

export async function updateFleet(id: number, payload: { name?: string; inn?: string | null; description?: string | null }): Promise<Fleet> {
    const { data } = await client.patch(`/fleets/${id}`, payload);
    return data.data ?? data;
}

export async function getFleetDrivers(fleetId: number): Promise<FleetDriver[]> {
    const { data } = await client.get(`/fleets/${fleetId}/drivers`);
    return data.data ?? [];
}

export async function attachDriver(fleetId: number, userId: number): Promise<void> {
    await client.post(`/fleets/${fleetId}/drivers`, { user_id: userId });
}

export async function detachDriver(fleetId: number, userId: number): Promise<void> {
    await client.delete(`/fleets/${fleetId}/drivers/${userId}`);
}

export async function getFleetAssignments(fleetId: number): Promise<FleetAssignment[]> {
    const { data } = await client.get(`/fleets/${fleetId}/assignments`);
    return data.data ?? [];
}

export async function getFleetAssignment(fleetId: number, assignmentId: number): Promise<FleetAssignment> {
    const { data } = await client.get(`/fleets/${fleetId}/assignments/${assignmentId}`);
    return data.data ?? data;
}

export async function getDriverAssignments(): Promise<FleetAssignment[]> {
    const { data } = await client.get('/assignments');
    return data.data ?? [];
}

export async function getDriverAssignment(assignmentId: number): Promise<FleetAssignment> {
    const { data } = await client.get(`/assignments/${assignmentId}`);
    return data.data ?? data;
}

export async function acceptAssignment(assignmentId: number): Promise<FleetAssignment> {
    const { data } = await client.post(`/assignments/${assignmentId}/accept`);
    return data.data ?? data;
}

export async function completeAssignment(assignmentId: number): Promise<FleetAssignment> {
    const { data } = await client.post(`/assignments/${assignmentId}/complete`);
    return data.data ?? data;
}

export async function cancelAssignment(assignmentId: number): Promise<FleetAssignment> {
    const { data } = await client.post(`/assignments/${assignmentId}/cancel`);
    return data.data ?? data;
}

export async function createAssignment(fleetId: number, payload: {
    driver_user_id: number;
    origin: string;
    origin_point?: GeoPoint;
    destination: string;
    destination_point?: GeoPoint;
    via_points?: GeoPoint[];
    planned_start_at?: string;
    vehicle_source?: 'driver' | 'fleet';
    vehicle_id?: number | null;
    comment?: string;
}): Promise<FleetAssignment> {
    const { data } = await client.post(`/fleets/${fleetId}/assignments`, payload);
    return data.data ?? data;
}

export async function updateAssignment(fleetId: number, assignmentId: number, payload: Partial<{
    status: string; comment: string;
}>): Promise<FleetAssignment> {
    const { data } = await client.patch(`/fleets/${fleetId}/assignments/${assignmentId}`, payload);
    return data.data ?? data;
}

export async function rateAssignment(fleetId: number, assignmentId: number, payload: {
    rating_stars: number;
    rating_comment?: string | null;
}): Promise<FleetAssignment> {
    const { data } = await client.post(`/fleets/${fleetId}/assignments/${assignmentId}/rating`, payload);
    return data.data ?? data;
}
