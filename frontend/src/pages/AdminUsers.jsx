import { useEffect, useState, useCallback } from "react";
import { ShieldCheck, UserPlus } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

const ROLES = ["admin", "reception", "nurse", "doctor", "lab_tech", "radiologist", "pharmacist", "billing", "dialysis_tech", "records_officer"];

export default function AdminUsers() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/users").then((res) => setUsers(res.data)).catch(() => setUsers([])).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader
        icon={ShieldCheck}
        title="User management"
        subtitle="Register staff accounts and control roles and access."
        action={<Button onClick={() => setShowForm((s) => !s)} icon={showForm ? undefined : UserPlus}>{showForm ? "Cancel" : "Register new user"}</Button>}
      />

      {showForm && <NewUserForm onSaved={() => { load(); setShowForm(false); }} />}

      {loading ? <LoadingRow /> : users.length === 0 ? (
        <EmptyState title="No users yet" />
      ) : (
        <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
              <tr>
                <th className="text-left px-4 py-3">Name</th>
                <th className="text-left px-4 py-3">Username</th>
                <th className="text-left px-4 py-3">Role</th>
                <th className="text-left px-4 py-3">Department</th>
                <th className="text-left px-4 py-3">Status</th>
                <th className="text-left px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => <UserRow key={u.id} user={u} onChanged={load} />)}
            </tbody>
          </table>
          </div>
        </Card>
      )}
    </div>
  );
}

function NewUserForm({ onSaved }) {
  const [form, setForm] = useState({ full_name: "", username: "", password: "", role: "reception", department: "", email: "", phone: "" });
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post("/users", form);
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Could not create user.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
        <Field label="Full name" required><Input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} /></Field>
        <Field label="Username" required><Input value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} /></Field>
        <Field label="Temporary password" required><Input type="text" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></Field>
        <Field label="Role">
          <Select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            {ROLES.map((r) => <option key={r} value={r}>{r.replace("_", " ")}</option>)}
          </Select>
        </Field>
        <Field label="Department"><Input value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })} /></Field>
        <Field label="Phone"><Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></Field>
        {error && <p className="text-sm text-alert md:col-span-3">{error}</p>}
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Creating…" : "Create user"}</Button>
      </form>
    </Card>
  );
}

function UserRow({ user, onChanged }) {
  const [editingRole, setEditingRole] = useState(false);
  const [role, setRole] = useState(user.role);

  async function saveRole() {
    await api.put(`/users/${user.id}`, { role });
    setEditingRole(false);
    onChanged();
  }
  async function toggleActive() {
    await api.put(`/users/${user.id}`, { is_active: !user.is_active });
    onChanged();
  }
  async function remove() {
    if (!window.confirm(`Delete ${user.full_name}? This cannot be undone.`)) return;
    await api.delete(`/users/${user.id}`);
    onChanged();
  }

  return (
    <tr className="border-t border-line">
      <td className="px-4 py-3 font-medium">{user.full_name}</td>
      <td className="px-4 py-3 text-ink/70">{user.username}</td>
      <td className="px-4 py-3">
        {editingRole ? (
          <div className="flex items-center gap-1">
            <Select value={role} onChange={(e) => setRole(e.target.value)} className="text-xs py-1">
              {ROLES.map((r) => <option key={r} value={r}>{r.replace("_", " ")}</option>)}
            </Select>
            <Button size="sm" onClick={saveRole}>Save</Button>
          </div>
        ) : (
          <button className="underline decoration-dotted" onClick={() => setEditingRole(true)}>
            <Badge>{user.role.replace("_", " ")}</Badge>
          </button>
        )}
      </td>
      <td className="px-4 py-3 text-ink/70">{user.department || "—"}</td>
      <td className="px-4 py-3">
        <Badge tone={user.is_active ? "success" : "muted"}>{user.is_active ? "Active" : "Disabled"}</Badge>
      </td>
      <td className="px-4 py-3">
        <div className="flex gap-2">
          <Button size="sm" variant="ghost" onClick={toggleActive}>{user.is_active ? "Disable" : "Enable"}</Button>
          <Button size="sm" variant="danger" onClick={remove}>Delete</Button>
        </div>
      </td>
    </tr>
  );
}
