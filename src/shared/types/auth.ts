export type AuthenticatedEmployee = {
  id: number;
  name: string;
  email: string;
};

export type AuthContextValue = {
  user: AuthenticatedEmployee | null;
  isAuthenticated: boolean;
};
