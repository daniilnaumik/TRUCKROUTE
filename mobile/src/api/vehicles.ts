import client from './client';

export interface Vehicle {
    id: number;
    title: string;
    type: string;
    model: string | null;
    fuel_type: string;
    allowed_fuel: string | null;
    tank_capacity_l: number;
    consumption_l_per_100: number;
    cruise_speed_kmh: number;
    curb_weight_t: number | null;
    restrictions: string | null;
    is_active: boolean;
}

export interface VehiclePayload {
    title: string;
    type: string;
    model?: string;
    fuel_type?: string;
    allowed_fuel?: string;
    tank_capacity_l: number;
    consumption_l_per_100: number;
    cruise_speed_kmh?: number;
    curb_weight_t?: number;
    restrictions?: string;
}

export async function getVehicles(): Promise<Vehicle[]> {
    const { data } = await client.get('/vehicles');
    return data.data ?? [];
}

export async function getVehicle(id: number): Promise<Vehicle> {
    const { data } = await client.get(`/vehicles/${id}`);
    return data.data ?? data;
}

export async function createVehicle(payload: VehiclePayload): Promise<Vehicle> {
    const { data } = await client.post('/vehicles', payload);
    return data.data ?? data;
}

export async function updateVehicle(id: number, payload: Partial<VehiclePayload>): Promise<Vehicle> {
    const { data } = await client.put(`/vehicles/${id}`, payload);
    return data.data ?? data;
}

export async function deleteVehicle(id: number): Promise<void> {
    await client.delete(`/vehicles/${id}`);
}

export async function activateVehicle(id: number): Promise<Vehicle> {
    const { data } = await client.post(`/vehicles/${id}/activate`);
    return data.data ?? data;
}
