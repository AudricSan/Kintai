import { apiFetch } from './client';

export type User = {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  [key: string]: unknown;
};

type LoginResponse = {
  token: string;
  token_id: number;
  expires_at: string | null;
  user: User;
};

export function login(email: string, password: string): Promise<LoginResponse> {
  return apiFetch<LoginResponse>('/auth/login', {
    method: 'POST',
    auth: false,
    body: { email, password, token_name: 'Kintai Mobile' },
  });
}

export function me(): Promise<User> {
  return apiFetch<User>('/auth/me');
}

export function logout(): Promise<void> {
  return apiFetch<void>('/auth/logout', { method: 'POST' });
}
