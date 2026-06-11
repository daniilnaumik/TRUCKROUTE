import client from './client';

export interface Cargo {
    id: number;
    title: string;
    flag: string;
    weight_t: number | null;
    requirements: string | null;
}

export interface CargoPayload {
    title: string;
    flag?: string;
    weight_t?: number;
    requirements?: string;
}

function normalizeCargo(raw: any): Cargo {
    return {
        ...raw,
        weight_t: raw.weight_t !== null && raw.weight_t !== undefined ? Number(raw.weight_t) : null,
        requirements: raw.requirements ?? null,
        flag: raw.flag || 'Обычный',
    };
}

export async function getCargos(): Promise<Cargo[]> {
    const { data } = await client.get('/cargos');
    return (data.data ?? []).map(normalizeCargo);
}

export async function createCargo(payload: CargoPayload): Promise<Cargo> {
    const { data } = await client.post('/cargos', payload);
    return normalizeCargo(data.data ?? data);
}

export async function updateCargo(id: number, payload: Partial<CargoPayload>): Promise<Cargo> {
    const { data } = await client.patch(`/cargos/${id}`, payload);
    return normalizeCargo(data.data ?? data);
}

export async function deleteCargo(id: number): Promise<void> {
    await client.delete(`/cargos/${id}`);
}
