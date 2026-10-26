export interface SharedProps {
  auth: {
    user: { id: number; name: string; role: string; role_label: string } | null;
    can: { review: boolean; viewAnalytics: boolean; export: boolean };
  };
  pendingReviewCount: number;
  notifications: {
    unread_count: number;
    items: { id: string; title: string; message: string; read: boolean; created_at: string }[];
  };
  flash: { success: string | null; error: string | null };
  [key: string]: unknown;
}
