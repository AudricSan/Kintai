import { Pressable, StyleSheet, Text } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAuth } from '@/src/auth/AuthContext';

export default function HomeScreen() {
  const { user, signOut } = useAuth();

  const displayName = [user?.first_name, user?.last_name].filter(Boolean).join(' ') || user?.email;

  return (
    <SafeAreaView style={styles.container}>
      <Text style={styles.greeting}>Bonjour {displayName}</Text>
      <Text style={styles.subtitle}>{user?.email}</Text>

      <Pressable style={styles.button} onPress={() => signOut()}>
        <Text style={styles.buttonText}>Se déconnecter</Text>
      </Pressable>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 24,
    backgroundColor: '#fff',
  },
  greeting: {
    fontSize: 24,
    fontWeight: '700',
    marginTop: 24,
  },
  subtitle: {
    fontSize: 16,
    color: '#667085',
    marginTop: 4,
    marginBottom: 32,
  },
  button: {
    backgroundColor: '#f04438',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
