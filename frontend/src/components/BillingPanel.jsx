import { useEffect, useState } from "react";
import api from "../services/api";
import { Card, Button, LoadingRow, Badge } from "./ui";

export default function BillingPanel({ encounterId, patientId, onSaved }) {
  const [loading, setLoading] = useState(true);
  const [charges, setCharges] = useState([]);
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    if (!patientId) return;
    setLoading(true);
    api.get(`/billing/patients/${patientId}/pending-charges`).then((res) => {
      setCharges(res.data.charges || []);
    }).catch(() => setCharges([])).finally(() => setLoading(false));
  }, [patientId]);

  async function createInvoice() {
    if (!encounterId || charges.length === 0) return;
    setCreating(true);
    try {
      const line_items = charges.map(c => ({ service_category: c.service_category, description: c.description, amount: c.amount, chargeable_type: c.chargeable_type, chargeable_id: c.chargeable_id }));
      await api.post('/billing/invoices', { encounter_id: encounterId, payer_type: 'cash', line_items });
      if (onSaved) onSaved();
    } catch (err) {
      // swallow — UI will refresh on next load
    } finally {
      setCreating(false);
    }
  }

  if (loading) return <LoadingRow label="Loading billing…" />;

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <p className="font-display text-lg">Billing</p>
        <div className="flex items-center gap-2">
          <Badge tone="muted">Pending: {charges.length}</Badge>
        </div>
      </div>

      {charges.length === 0 ? (
        <p className="text-sm text-ink/50">No pending charges to bill.</p>
      ) : (
        <div className="space-y-2 mb-3">
          {charges.map((c) => (
            <div key={`${c.chargeable_type}-${c.chargeable_id}`} className="flex justify-between text-sm border p-2 rounded">
              <div>
                <div className="font-semibold">{c.description}</div>
                <div className="text-xs text-ink/50">{c.service_category}</div>
              </div>
              <div className="flex items-center gap-2">
                <div className="mr-2 font-mono">{Number(c.amount).toLocaleString()}</div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="flex justify-end">
        <Button tone="primary" onClick={createInvoice} disabled={creating || charges.length === 0}>{creating ? 'Creating…' : 'Create invoice'}</Button>
      </div>
    </Card>
  );
}
