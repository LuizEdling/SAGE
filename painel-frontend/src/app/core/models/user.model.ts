export interface User {
  id: number;
  name: string;
  email: string;
  is_master: boolean;
  active: boolean;
  created_at?: string;
}

export interface StoreUserPayload {
  name: string;
  email: string;
  password: string;
  is_master: boolean;
}

export interface UpdateUserPayload {
  name: string;
  email: string;
  is_master: boolean;
}
