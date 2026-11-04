import { http } from './http';
import type { Role, User } from '@/stores/auth';

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
}

export interface ListParams {
    page?: number;
    per_page?: number;
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
    status?: string;
    from?: string;
    to?: string;
    without_invoice?: 0 | 1;
}

export interface DashboardData {
    period: { from: string; to: string; days: number };
    kpis: {
        order_value: { value: number; change: number | null };
        orders: { value: number; change: number | null };
        customers: { value: number; new: number };
        overdue_invoices: { value: number; amount: number };
    };
    chart: { date: string; order_value: number }[];
    recent_orders: { id: number; number: string; customer: string; status: string; ordered_at: string; items_count: number; total: number }[];
}

export interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    city: string | null;
    notes: string | null;
    orders_count?: number;
    created_at: string;
}

export interface OrderItem {
    id?: number;
    product_name: string;
    quantity: number;
    unit_price: number;
    line_total?: number;
}

export interface Order {
    id: number;
    number: string;
    customer_id: number;
    customer: { id: number; name: string } | null;
    status: 'pending' | 'processing' | 'completed' | 'cancelled';
    ordered_at: string;
    total: number;
    notes: string | null;
    items_count?: number;
    items?: OrderItem[];
    invoice?: { id: number; number: string; status: Invoice['status'] } | null;
}

export interface Invoice {
    id: number;
    number: string;
    order_id: number;
    order: (Pick<Order, 'id' | 'number' | 'ordered_at' | 'total'> & { items?: OrderItem[] }) | null;
    customer: Pick<Customer, 'id' | 'name' | 'email' | 'phone' | 'city'> | null;
    status: 'draft' | 'paid' | 'overdue';
    amount: number;
    issued_at: string;
    due_at: string;
    notes: string | null;
}

export interface ErrorEvent {
    id: number;
    fingerprint: string;
    exception_class: string;
    message: string;
    file: string;
    line: number;
    count: number;
    first_seen_at: string;
    last_seen_at: string;
    resolved_at: string | null;
    url?: string | null;
    method?: string | null;
    trace?: { file: string | null; line: number | null; function: string }[];
}

function clean(params: ListParams): ListParams {
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== undefined && v !== null)) as ListParams;
}

function resource<T, Payload>(path: string) {
    return {
        list: async (params: ListParams) => (await http.get<Paginated<T>>(`/api/${path}`, { params: clean(params) })).data,
        get: async (id: number | string) => (await http.get<{ data: T }>(`/api/${path}/${id}`)).data.data,
        create: async (payload: Payload) => (await http.post<{ data: T }>(`/api/${path}`, payload)).data.data,
        update: async (id: number, payload: Payload) => (await http.put<{ data: T }>(`/api/${path}/${id}`, payload)).data.data,
        remove: async (id: number) => {
            await http.delete(`/api/${path}/${id}`);
        },
    };
}

export type CustomerPayload = Pick<Customer, 'name' | 'email' | 'phone' | 'city' | 'notes'>;
export type OrderPayload = { customer_id: number | null; status: Order['status']; ordered_at: string; notes: string | null; items: OrderItem[] };
export type InvoicePayload = { order_id: number | null; status: Invoice['status']; issued_at: string; due_at: string; notes: string | null };

export const api = {
    dashboard: async (params: { from?: string; to?: string } = {}) =>
        (await http.get<{ data: DashboardData }>('/api/dashboard', { params: clean(params) })).data.data,
    customers: resource<Customer, CustomerPayload>('customers'),
    orders: resource<Order, OrderPayload>('orders'),
    invoices: {
        ...resource<Invoice, InvoicePayload>('invoices'),
        email: async (id: number) => (await http.post<{ message: string }>(`/api/invoices/${id}/email`)).data.message,
        reopen: async (id: number) => (await http.post<{ data: Invoice }>(`/api/invoices/${id}/reopen`)).data.data,
        pdfUrl: (id: number, locale: string) => `/documents/invoices/${id}.pdf?lang=${locale}`,
        emailPreviewUrl: (id: number, locale: string) => `/documents/invoices/${id}/email?lang=${locale}`,
    },
    users: {
        list: async () => (await http.get<{ data: (User & { created_at: string })[] }>('/api/users')).data.data,
        updateRole: async (id: number, role: Role) => (await http.patch<{ data: User }>(`/api/users/${id}`, { role })).data.data,
    },
    errors: {
        list: async (params: ListParams) => (await http.get<Paginated<ErrorEvent>>('/api/error-events', { params: clean(params) })).data,
        get: async (id: number | string) => (await http.get<{ data: ErrorEvent }>(`/api/error-events/${id}`)).data.data,
        resolve: async (id: number, resolved: boolean) =>
            (await http.patch<{ data: ErrorEvent }>(`/api/error-events/${id}`, { resolved })).data.data,
        trigger: async () => http.post('/api/error-events/trigger'),
    },
};
