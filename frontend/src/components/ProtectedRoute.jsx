import { Navigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import AppLayout from "./AppLayout";
import { LoadingRow } from "./ui";

export default function ProtectedRoute({ children, roles }) {
  const { user, loading, hasRole } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-paper">
        <LoadingRow label="Loading NullCare…" />
      </div>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  if (roles && !hasRole(...roles)) {
    return (
      <AppLayout>
        <div className="text-center py-20">
          <p className="font-display text-xl">You don't have access to this page.</p>
          <p className="text-sm text-ink/50 mt-2">
            This section is limited to {roles.join(", ")}. Contact an administrator if you believe this is a mistake.
          </p>
        </div>
      </AppLayout>
    );
  }

  return <AppLayout>{children}</AppLayout>;
}
