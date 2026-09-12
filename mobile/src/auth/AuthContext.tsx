import { createContext, useContext, useEffect, useMemo, useState, type PropsWithChildren } from 'react';

import * as authApi from '@/src/api/auth';
import { clearToken, getToken, setToken } from '@/src/api/client';

type AuthContextValue = {
  user: authApi.User | null;
  isLoading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<authApi.User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Au démarrage : un token déjà stocké (session précédente) est revalidé auprès
  // de /auth/me plutôt que supposé valide, au cas où il a expiré ou été révoqué
  // entre-temps (autre appareil, /profile côté web...).
  useEffect(() => {
    (async () => {
      const token = await getToken();
      if (!token) {
        setIsLoading(false);
        return;
      }
      try {
        setUser(await authApi.me());
      } catch {
        await clearToken();
      } finally {
        setIsLoading(false);
      }
    })();
  }, []);

  const signIn = async (email: string, password: string) => {
    const { token, user: loggedInUser } = await authApi.login(email, password);
    await setToken(token);
    setUser(loggedInUser);
  };

  const signOut = async () => {
    try {
      await authApi.logout();
    } catch {
      // Le token est peut-être déjà invalide côté serveur (expiré, révoqué) —
      // on nettoie quand même la session locale.
    }
    await clearToken();
    setUser(null);
  };

  const value = useMemo(() => ({ user, isLoading, signIn, signOut }), [user, isLoading]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth() doit être utilisé à l\'intérieur de <AuthProvider>.');
  }
  return ctx;
}
