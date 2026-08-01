import { useState, useEffect, useCallback } from "react";
import {
  Package, Boxes, Wrench, AlertTriangle, PackagePlus, MinusCircle,
  Siren, CheckCircle2, ChevronDown, ChevronUp, CalendarClock,
} from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";
import { useAuth } from "../context/AuthContext";

const CATEGORY_LABELS = {
  pharmacy: "Pharmacy stock", laboratory: "Laboratory reagents", imaging: "Imaging consumables",
  theatre: "Theatre consumables", ward: "Ward supplies",
};
const CATEGORIES = Object.keys(CATEGORY_LABELS);

export default function Inventory() {
  const { hasRole } = useAuth();
  const canManage = hasRole("pharmacist", "lab_tech", "radiologist", "nurse", "dialysis_tech");
  const [tab, setTab] = useState("supplies");

  return (
    <div className="space-y-5">
      <PageHeader
        icon={Package}
        title="Inventory & Equipment"
        subtitle="Stock across pharmacy, laboratory, imaging, theatre and ward supplies — plus biomedical equipment maintenance and downtime."
      />

      <div className="flex gap-2">
        <button
          onClick={() => setTab("supplies")}
          className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors ${
            tab === "supplies" ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"
          }`}
        >
          <Boxes size={14} /> Supplies
        </button>
        <button
          onClick={() => setTab("equipment")}
          className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors ${
            tab === "equipment" ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"
          }`}
        >
          <Wrench size={14} /> Equipment
        </button>
      </div>

      {tab === "supplies" ? <SuppliesTab canManage={canManage} /> : <EquipmentTab canManage={canManage} />}
    </div>
  );
}

// ==================== SUPPLIES ====================

function SuppliesTab({ canManage }) {
  const [items, setItems] = useState([]);
  const [alerts, setAlerts] = useState(null);
  const [category, setCategory] = useState("");
  const [loading, setLoading] = useState(true);
  const [showNewItem, setShowNewItem] = useState(false);
  const [expandedId, setExpandedId] = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/inventory/items", { params: category ? { category } : {} }),
      api.get("/inventory/alerts"),
    ])
      .then(([iRes, aRes]) => { setItems(iRes.data); setAlerts(aRes.data); })
      .catch(() => { setItems([]); setAlerts(null); })
      .finally(() => setLoading(false));
  }, [category]);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      {alerts && (alerts.low_stock.length > 0 || alerts.expiring_batches.length > 0) && (
        <Card className="border-clay/30 bg-clay/5">
          <p className="font-semibold text-clay flex items-center gap-2 mb-2">
            <AlertTriangle size={16} /> Stock alerts
          </p>
          <div className="grid md:grid-cols-2 gap-3 text-sm">
            {alerts.low_stock.length > 0 && (
              <div>
                <p className="text-xs font-semibold text-ink/50 uppercase mb-1">At or below reorder level</p>
                <ul className="space-y-1">
                  {alerts.low_stock.map((s) => (
                    <li key={s.id}>{s.name} — {s.quantity_on_hand} {s.unit} left</li>
                  ))}
                </ul>
              </div>
            )}
            {alerts.expiring_batches.length > 0 && (
              <div>
                <p className="text-xs font-semibold text-ink/50 uppercase mb-1">Expiring within 90 days</p>
                <ul className="space-y-1">
                  {alerts.expiring_batches.map((b) => (
                    <li key={b.id}>
                      {b.item_name} ({b.batch_number || "no batch #"}) — {b.expiry_date}
                      {b.is_expired && <Badge tone="critical" className="ml-1.5">expired</Badge>}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        </Card>
      )}

      <div className="flex items-center justify-between flex-wrap gap-3">
        <div className="flex gap-2 flex-wrap">
          <button
            onClick={() => setCategory("")}
            className={`px-3 py-1.5 rounded-full text-xs font-medium ${category === "" ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"}`}
          >
            All categories
          </button>
          {CATEGORIES.map((c) => (
            <button
              key={c}
              onClick={() => setCategory(c)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium ${category === c ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"}`}
            >
              {CATEGORY_LABELS[c]}
            </button>
          ))}
        </div>
        {canManage && (
          <Button size="sm" icon={PackagePlus} onClick={() => setShowNewItem((s) => !s)}>
            {showNewItem ? "Cancel" : "Add item"}
          </Button>
        )}
      </div>

      {showNewItem && <NewItemForm onSaved={() => { setShowNewItem(false); load(); }} />}

      {loading ? <LoadingRow /> : items.length === 0 ? (
        <EmptyState icon={Boxes} title="No items in this category yet" />
      ) : (
        <div className="space-y-2">
          {items.map((item) => (
            <ItemRow
              key={item.id}
              item={item}
              canManage={canManage}
              expanded={expandedId === item.id}
              onToggle={() => setExpandedId(expandedId === item.id ? null : item.id)}
              onChanged={load}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function NewItemForm({ onSaved }) {
  const [form, setForm] = useState({ name: "", category: "pharmacy", unit: "units", reorder_level: 10, department: "", is_controlled: false });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post("/inventory/items", form);
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't create the item.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3 items-end">
        <Field label="Item name" required><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Category">
          <Select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
            {CATEGORIES.map((c) => <option key={c} value={c}>{CATEGORY_LABELS[c]}</option>)}
          </Select>
        </Field>
        <Field label="Unit"><Input value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })} placeholder="e.g. tablets, kits, boxes" /></Field>
        <Field label="Reorder level"><Input type="number" value={form.reorder_level} onChange={(e) => setForm({ ...form, reorder_level: Number(e.target.value) })} /></Field>
        <Field label="Stored at / department"><Input value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })} /></Field>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={form.is_controlled} onChange={(e) => setForm({ ...form, is_controlled: e.target.checked })} /> Controlled item
        </label>
        {error && <p className="text-sm text-alert md:col-span-3">{error}</p>}
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Saving…" : "Add item"}</Button>
      </form>
    </Card>
  );
}

function ItemRow({ item, canManage, expanded, onToggle, onChanged }) {
  return (
    <Card className="p-0 overflow-hidden">
      <button onClick={onToggle} className="w-full flex items-center justify-between gap-3 px-4 py-3 text-left hover:bg-surface-alt/60 transition-colors">
        <div className="flex items-center gap-3 min-w-0">
          {expanded ? <ChevronUp size={16} className="text-ink/40 shrink-0" /> : <ChevronDown size={16} className="text-ink/40 shrink-0" />}
          <div className="min-w-0">
            <p className="font-medium truncate">{item.name}</p>
            <p className="text-xs text-ink/45">{CATEGORY_LABELS[item.category] || item.category}{item.department ? ` · ${item.department}` : ""}</p>
          </div>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {item.nearest_expiry && <Badge tone="muted"><CalendarClock size={11} />{item.nearest_expiry}</Badge>}
          {item.low_stock && <Badge tone="warning">low stock</Badge>}
          <Badge tone={item.low_stock ? "warning" : "success"}>{item.quantity_on_hand} {item.unit}</Badge>
        </div>
      </button>
      {expanded && <ItemDetail itemId={item.id} canManage={canManage} onChanged={onChanged} />}
    </Card>
  );
}

function ItemDetail({ itemId, canManage, onChanged }) {
  const [detail, setDetail] = useState(null);
  const [showReceive, setShowReceive] = useState(false);
  const [showConsume, setShowConsume] = useState(false);

  const load = useCallback(() => {
    api.get(`/inventory/items/${itemId}`).then((res) => setDetail(res.data)).catch(() => setDetail(null));
  }, [itemId]);

  useEffect(() => { load(); }, [load]);

  function refresh() {
    load();
    onChanged();
    setShowReceive(false);
    setShowConsume(false);
  }

  if (!detail) return <div className="px-4 py-3 border-t border-line"><LoadingRow label="Loading batches…" /></div>;

  return (
    <div className="border-t border-line px-4 py-3 space-y-3">
      {detail.batches.length === 0 ? (
        <p className="text-sm text-ink/40">No batches received yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[480px] text-sm">
            <thead className="text-xs text-ink/50 uppercase">
              <tr><th className="text-left py-1">Batch #</th><th className="text-left py-1">On hand</th><th className="text-left py-1">Expiry</th><th className="text-left py-1">Supplier</th></tr>
            </thead>
            <tbody>
              {detail.batches.map((b) => (
                <tr key={b.id} className="border-t border-line">
                  <td className="py-1.5">{b.batch_number || "—"}</td>
                  <td className="py-1.5">{b.quantity_on_hand} / {b.quantity_received}</td>
                  <td className="py-1.5">{b.expiry_date || "—"}</td>
                  <td className="py-1.5 text-ink/60">{b.supplier || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {canManage && (
        <div className="flex gap-2">
          <Button size="sm" variant="secondary" icon={PackagePlus} onClick={() => { setShowReceive((s) => !s); setShowConsume(false); }}>
            Receive stock
          </Button>
          <Button size="sm" variant="secondary" icon={MinusCircle} onClick={() => { setShowConsume((s) => !s); setShowReceive(false); }}>
            Record consumption
          </Button>
        </div>
      )}

      {showReceive && <ReceiveBatchForm itemId={itemId} onSaved={refresh} />}
      {showConsume && <ConsumeForm itemId={itemId} onSaved={refresh} />}
    </div>
  );
}

function ReceiveBatchForm({ itemId, onSaved }) {
  const [form, setForm] = useState({ batch_number: "", quantity_received: "", expiry_date: "", supplier: "", unit_cost: "" });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post(`/inventory/items/${itemId}/batches`, {
        ...form, quantity_received: Number(form.quantity_received),
        unit_cost: form.unit_cost ? Number(form.unit_cost) : undefined,
      });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't receive this batch.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="grid md:grid-cols-3 gap-2 bg-surface-alt rounded-lg p-3">
      <Field label="Batch / lot #"><Input value={form.batch_number} onChange={(e) => setForm({ ...form, batch_number: e.target.value })} /></Field>
      <Field label="Quantity received" required><Input type="number" min="1" value={form.quantity_received} onChange={(e) => setForm({ ...form, quantity_received: e.target.value })} /></Field>
      <Field label="Expiry date"><Input type="date" value={form.expiry_date} onChange={(e) => setForm({ ...form, expiry_date: e.target.value })} /></Field>
      <Field label="Supplier"><Input value={form.supplier} onChange={(e) => setForm({ ...form, supplier: e.target.value })} /></Field>
      <Field label="Unit cost (optional)"><Input type="number" step="0.01" value={form.unit_cost} onChange={(e) => setForm({ ...form, unit_cost: e.target.value })} /></Field>
      {error && <p className="text-sm text-alert md:col-span-3">{error}</p>}
      <Button type="submit" size="sm" disabled={saving} className="md:col-span-3">{saving ? "Saving…" : "Receive batch"}</Button>
    </form>
  );
}

function ConsumeForm({ itemId, onSaved }) {
  const [quantity, setQuantity] = useState("");
  const [department, setDepartment] = useState("");
  const [reason, setReason] = useState("");
  const [encounterId, setEncounterId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    if (!quantity || Number(quantity) <= 0) {
      setError("Enter a quantity greater than zero.");
      return;
    }
    setSaving(true);
    try {
      await api.post("/inventory/consume", {
        item_id: itemId, quantity: Number(quantity), department, reason,
        encounter_id: encounterId,
      });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't record consumption.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-2 bg-surface-alt rounded-lg p-3">
      <p className="text-xs text-ink/50">Link to a patient's visit (optional) — leave blank for general/ward use not tied to one patient.</p>
      <PatientLookup requireEncounter onSelect={({ encounterId }) => setEncounterId(encounterId)} />
      <div className="grid md:grid-cols-3 gap-2">
        <Field label="Quantity used" required><Input type="number" min="1" value={quantity} onChange={(e) => setQuantity(e.target.value)} /></Field>
        <Field label="Department"><Input value={department} onChange={(e) => setDepartment(e.target.value)} placeholder="e.g. laboratory" /></Field>
        <Field label="Reason"><Input value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      </div>
      {error && <p className="text-sm text-alert">{error}</p>}
      <Button type="submit" size="sm" disabled={saving}>{saving ? "Recording…" : "Record consumption"}</Button>
    </form>
  );
}

// ==================== EQUIPMENT ====================

function EquipmentTab({ canManage }) {
  const [equipment, setEquipment] = useState([]);
  const [dashboard, setDashboard] = useState(null);
  const [downtime, setDowntime] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showNewEquipment, setShowNewEquipment] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/inventory/equipment"),
      api.get("/inventory/equipment/dashboard"),
      api.get("/inventory/downtime", { params: { status: "open" } }),
    ])
      .then(([eRes, dRes, dtRes]) => { setEquipment(eRes.data); setDashboard(dRes.data); setDowntime(dtRes.data); })
      .catch(() => { setEquipment([]); setDashboard(null); setDowntime([]); })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      {dashboard && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <StatCard label="Total equipment" value={dashboard.total_equipment} />
          <StatCard label="Operational" value={dashboard.operational} tone="success" />
          <StatCard label="Under maintenance" value={dashboard.under_maintenance} tone="warning" />
          <StatCard label="Down" value={dashboard.down} tone="critical" />
          <StatCard label="Avg. downtime (hrs)" value={dashboard.avg_resolved_downtime_hours} />
        </div>
      )}

      {downtime.length > 0 && (
        <Card className="border-alert/30 bg-alert/5">
          <p className="font-semibold text-alert flex items-center gap-2 mb-2"><Siren size={16} /> Open downtime reports</p>
          <div className="space-y-2 text-sm">
            {downtime.map((d) => (
              <div key={d.id} className="flex items-center justify-between">
                <span>{d.equipment_name} ({d.department}) — {d.reason}</span>
                {canManage && <ResolveButton reportId={d.id} onResolved={load} />}
              </div>
            ))}
          </div>
        </Card>
      )}

      {dashboard?.maintenance_due_soon?.length > 0 && (
        <Card className="border-clay/30 bg-clay/5">
          <p className="font-semibold text-clay flex items-center gap-2 mb-2"><CalendarClock size={16} /> Maintenance due within 30 days</p>
          <ul className="text-sm space-y-1">
            {dashboard.maintenance_due_soon.map((e) => (
              <li key={e.id}>{e.name} — due {e.next_maintenance_due}</li>
            ))}
          </ul>
        </Card>
      )}

      {canManage && (
        <Button size="sm" onClick={() => setShowNewEquipment((s) => !s)}>
          {showNewEquipment ? "Cancel" : "Register equipment"}
        </Button>
      )}
      {showNewEquipment && <NewEquipmentForm onSaved={() => { setShowNewEquipment(false); load(); }} />}

      {loading ? <LoadingRow /> : equipment.length === 0 ? (
        <EmptyState icon={Wrench} title="No equipment registered yet" />
      ) : (
        <div className="space-y-2">
          {equipment.map((e) => (
            <EquipmentRow key={e.id} equipment={e} canManage={canManage} onChanged={load} />
          ))}
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, tone }) {
  const toneClass = { success: "text-moss", warning: "text-clay", critical: "text-alert" }[tone] || "";
  return (
    <Card className="p-4">
      <p className="text-xs text-ink/50 mb-1">{label}</p>
      <p className={`font-display text-2xl ${toneClass}`}>{value}</p>
    </Card>
  );
}

function ResolveButton({ reportId, onResolved }) {
  const [saving, setSaving] = useState(false);
  async function resolve() {
    setSaving(true);
    try {
      await api.post(`/inventory/downtime/${reportId}/resolve`);
      onResolved();
    } finally {
      setSaving(false);
    }
  }
  return <Button size="sm" variant="ghost" icon={CheckCircle2} onClick={resolve} disabled={saving}>Resolve</Button>;
}

function NewEquipmentForm({ onSaved }) {
  const [form, setForm] = useState({ name: "", equipment_type: "", department: "", serial_number: "", install_date: "" });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post("/inventory/equipment", form);
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't register this equipment.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
        <Field label="Name" required><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Type"><Input value={form.equipment_type} onChange={(e) => setForm({ ...form, equipment_type: e.target.value })} placeholder="e.g. Dialysis machine" /></Field>
        <Field label="Department"><Input value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })} /></Field>
        <Field label="Serial number"><Input value={form.serial_number} onChange={(e) => setForm({ ...form, serial_number: e.target.value })} /></Field>
        <Field label="Install date"><Input type="date" value={form.install_date} onChange={(e) => setForm({ ...form, install_date: e.target.value })} /></Field>
        {error && <p className="text-sm text-alert md:col-span-3">{error}</p>}
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Saving…" : "Register equipment"}</Button>
      </form>
    </Card>
  );
}

function EquipmentRow({ equipment, canManage, onChanged }) {
  const [expanded, setExpanded] = useState(false);
  const tone = { operational: "success", under_maintenance: "warning", down: "critical" }[equipment.status] || "muted";

  return (
    <Card className="p-0 overflow-hidden">
      <button onClick={() => setExpanded((s) => !s)} className="w-full flex items-center justify-between gap-3 px-4 py-3 text-left hover:bg-surface-alt/60 transition-colors">
        <div className="flex items-center gap-3 min-w-0">
          {expanded ? <ChevronUp size={16} className="text-ink/40 shrink-0" /> : <ChevronDown size={16} className="text-ink/40 shrink-0" />}
          <div className="min-w-0">
            <p className="font-medium truncate">{equipment.name}</p>
            <p className="text-xs text-ink/45">{equipment.equipment_type}{equipment.department ? ` · ${equipment.department}` : ""}{equipment.serial_number ? ` · SN ${equipment.serial_number}` : ""}</p>
          </div>
        </div>
        <Badge tone={tone}>{equipment.status.replace("_", " ")}</Badge>
      </button>
      {expanded && <EquipmentDetail equipment={equipment} canManage={canManage} onChanged={onChanged} />}
    </Card>
  );
}

function EquipmentDetail({ equipment, canManage, onChanged }) {
  const [records, setRecords] = useState([]);
  const [showMaintenance, setShowMaintenance] = useState(false);
  const [showDowntime, setShowDowntime] = useState(false);

  const load = useCallback(() => {
    api.get(`/inventory/equipment/${equipment.id}/maintenance`).then((res) => setRecords(res.data)).catch(() => setRecords([]));
  }, [equipment.id]);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="border-t border-line px-4 py-3 space-y-3">
      {records.length === 0 ? (
        <p className="text-sm text-ink/40">No maintenance logged yet.</p>
      ) : (
        <ul className="text-sm space-y-1.5">
          {records.map((r) => (
            <li key={r.id} className="border-b border-line last:border-0 pb-1.5">
              <Badge tone="muted" className="mr-1.5">{r.maintenance_type}</Badge>
              {r.performed_by_name} — {new Date(r.performed_at).toLocaleDateString()}
              {r.notes && <span className="text-ink/50"> · {r.notes}</span>}
              {r.cost != null && <span className="text-ink/50"> · MWK {r.cost.toLocaleString()}</span>}
            </li>
          ))}
        </ul>
      )}

      {canManage && (
        <div className="flex gap-2">
          <Button size="sm" variant="secondary" onClick={() => { setShowMaintenance((s) => !s); setShowDowntime(false); }}>
            Log maintenance
          </Button>
          {equipment.status !== "down" && (
            <Button size="sm" variant="danger" icon={Siren} onClick={() => { setShowDowntime((s) => !s); setShowMaintenance(false); }}>
              Report downtime
            </Button>
          )}
        </div>
      )}

      {showMaintenance && (
        <MaintenanceForm equipmentId={equipment.id} onSaved={() => { setShowMaintenance(false); load(); onChanged(); }} />
      )}
      {showDowntime && (
        <DowntimeForm equipmentId={equipment.id} onSaved={() => { setShowDowntime(false); onChanged(); }} />
      )}
    </div>
  );
}

function MaintenanceForm({ equipmentId, onSaved }) {
  const [form, setForm] = useState({ maintenance_type: "routine", performed_by_name: "", notes: "", cost: "", next_maintenance_due: "" });
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/inventory/equipment/${equipmentId}/maintenance`, { ...form, cost: form.cost ? Number(form.cost) : undefined });
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="grid md:grid-cols-2 gap-2 bg-surface-alt rounded-lg p-3">
      <Select value={form.maintenance_type} onChange={(e) => setForm({ ...form, maintenance_type: e.target.value })}>
        <option value="routine">Routine</option><option value="repair">Repair</option>
        <option value="inspection">Inspection</option><option value="calibration">Calibration</option>
      </Select>
      <Input placeholder="Performed by" value={form.performed_by_name} onChange={(e) => setForm({ ...form, performed_by_name: e.target.value })} />
      <Textarea placeholder="Notes" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} className="md:col-span-2" />
      <Input type="number" placeholder="Cost (optional)" value={form.cost} onChange={(e) => setForm({ ...form, cost: e.target.value })} />
      <Field label="Next maintenance due"><Input type="date" value={form.next_maintenance_due} onChange={(e) => setForm({ ...form, next_maintenance_due: e.target.value })} /></Field>
      <Button type="submit" size="sm" disabled={saving} className="md:col-span-2">{saving ? "Saving…" : "Log maintenance"}</Button>
    </form>
  );
}

function DowntimeForm({ equipmentId, onSaved }) {
  const [reason, setReason] = useState("");
  const [impact, setImpact] = useState("");
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/inventory/equipment/${equipmentId}/downtime`, { reason, impact_notes: impact });
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-2 bg-alert/5 border border-alert/20 rounded-lg p-3">
      <Textarea placeholder="Reason for downtime" value={reason} onChange={(e) => setReason(e.target.value)} />
      <Textarea placeholder="Clinical/operational impact" value={impact} onChange={(e) => setImpact(e.target.value)} />
      <Button type="submit" size="sm" variant="danger" disabled={saving}>{saving ? "Reporting…" : "Report downtime"}</Button>
    </form>
  );
}
