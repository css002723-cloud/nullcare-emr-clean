import { useEffect, useState, useCallback } from "react";
import { Receipt } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

const CATEGORIES = ["consultation", "laboratory", "imaging", "pharmacy", "procedure", "theatre", "admission", "bed", "consumables"];

export default function Billing() {
  const [invoices, setInvoices] = useState([]);
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/billing/invoices"),
      api.get("/billing/unpaid-report").catch(() => ({ data: null })),
    ])
      .then(([iRes, rRes]) => { setInvoices(iRes.data); setReport(rRes.data); })
      .catch(() => setInvoices([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader icon={Receipt} title="Billing & revenue cycle" subtitle="Service-based invoicing, payment tracking, and outstanding balances." />

      {report && (
        <Card className="bg-surface-alt border-line">
          <p className="text-sm text-ink/60">Outstanding across {report.count} invoice(s)</p>
          <p className="font-display text-2xl">MWK {report.outstanding_total.toLocaleString()}</p>
        </Card>
      )}

      <NewInvoicePanel onSaved={load} />

      {loading ? <LoadingRow /> : invoices.length === 0 ? (
        <EmptyState title="No invoices yet" />
      ) : (
        <div className="space-y-3">
          {invoices.map((inv) => <InvoiceRow key={inv.id} invoice={inv} onSaved={load} />)}
        </div>
      )}
    </div>
  );
}

function NewInvoicePanel({ onSaved }) {
  const [encounterId, setEncounterId] = useState("");
  const [payerType, setPayerType] = useState("cash");
  const [items, setItems] = useState([{ service_category: "consultation", description: "", amount: "" }]);
  const [saving, setSaving] = useState(false);

  function updateItem(i, field, value) {
    setItems((list) => list.map((it, idx) => (idx === i ? { ...it, [field]: value } : it)));
  }
  function addItem() {
    setItems((list) => [...list, { service_category: "laboratory", description: "", amount: "" }]);
  }

  async function submit(e) {
    e.preventDefault();
    if (!encounterId) return;
    setSaving(true);
    try {
      await api.post("/billing/invoices", {
        encounter_id: Number(encounterId), payer_type: payerType,
        line_items: items.filter((i) => i.amount).map((i) => ({ ...i, amount: Number(i.amount) })),
      });
      setEncounterId("");
      setItems([{ service_category: "consultation", description: "", amount: "" }]);
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">Create invoice</p>
      <form onSubmit={submit} className="space-y-3">
        <div className="grid md:grid-cols-2 gap-3">
          <Field label="Encounter ID" required><Input value={encounterId} onChange={(e) => setEncounterId(e.target.value)} /></Field>
          <Field label="Payer type">
            <Select value={payerType} onChange={(e) => setPayerType(e.target.value)}>
              <option value="cash">Cash</option><option value="insurance">Insurance</option>
              <option value="institutional">Institutional</option><option value="waiver">Waiver</option>
            </Select>
          </Field>
        </div>
        {items.map((item, i) => (
          <div key={i} className="grid grid-cols-3 gap-2">
            <Select value={item.service_category} onChange={(e) => updateItem(i, "service_category", e.target.value)}>
              {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
            </Select>
            <Input placeholder="Description" value={item.description} onChange={(e) => updateItem(i, "description", e.target.value)} />
            <Input type="number" placeholder="Amount (MWK)" value={item.amount} onChange={(e) => updateItem(i, "amount", e.target.value)} />
          </div>
        ))}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={addItem}>+ Add line item</Button>
          <Button type="submit" disabled={saving}>{saving ? "Creating…" : "Create invoice"}</Button>
        </div>
      </form>
    </Card>
  );
}

function InvoiceRow({ invoice, onSaved }) {
  const [amount, setAmount] = useState("");
  async function pay() {
    if (!amount) return;
    await api.post(`/billing/invoices/${invoice.id}/pay`, { amount: Number(amount) });
    setAmount("");
    onSaved();
  }
  async function waive() {
    await api.post(`/billing/invoices/${invoice.id}/waive`);
    onSaved();
  }

  return (
    <Card>
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <p className="font-semibold mrn-mono">{invoice.invoice_number}</p>
          <p className="text-xs text-ink/50">{invoice.payer_type} {invoice.payer_name ? `— ${invoice.payer_name}` : ""}</p>
        </div>
        <Badge tone={invoice.status === "paid" ? "success" : invoice.status === "waived" ? "muted" : "warning"}>{invoice.status}</Badge>
      </div>
      <p className="text-sm mt-2">Total: MWK {invoice.total_amount.toLocaleString()} · Paid: MWK {invoice.amount_paid.toLocaleString()}</p>
      {invoice.status !== "paid" && invoice.status !== "waived" && (
        <div className="flex gap-2 mt-2">
          <Input className="w-32" type="number" placeholder="Amount" value={amount} onChange={(e) => setAmount(e.target.value)} />
          <Button size="sm" onClick={pay}>Record payment</Button>
          <Button size="sm" variant="ghost" onClick={waive}>Waive</Button>
        </div>
      )}
    </Card>
  );
}
