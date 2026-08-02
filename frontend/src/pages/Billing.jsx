import { useEffect, useState, useCallback } from "react";
import { Receipt } from "lucide-react";
import api from "../services/api";
import {
  Card,
  Badge,
  Button,
  Field,
  Input,
  Select,
  LoadingRow,
  EmptyState,
} from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";

const CATEGORIES = [
  "consultation",
  "laboratory",
  "imaging",
  "pharmacy",
  "procedure",
  "theatre",
  "admission",
  "bed",
  "consumables",
];

export default function Billing() {
  const [invoices, setInvoices] = useState([]);
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);

    Promise.all([
      api.get("/billing/invoices"),
      api
        .get("/billing/unpaid-report")
        .catch(() => ({ data: null })),
    ])
      .then(([iRes, rRes]) => {
        setInvoices(iRes.data);
        setReport(rRes.data);
      })
      .catch(() => setInvoices([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader
        icon={Receipt}
        title="Billing & revenue cycle"
        subtitle="Service-based invoicing, payment tracking, and outstanding balances."
      />

      {report && (
        <Card className="bg-surface-alt border-line">
          <p className="text-sm text-ink/60">
            Outstanding across {report.count} invoice(s)
          </p>

          <p className="font-display text-2xl">
            MWK {report.outstanding_total.toLocaleString()}
          </p>
        </Card>
      )}

      <NewInvoicePanel onSaved={load} />

      {loading ? (
        <LoadingRow />
      ) : invoices.length === 0 ? (
        <EmptyState title="No invoices yet" />
      ) : (
        <div className="space-y-3">
          {invoices.map((inv) => (
            <InvoiceRow
              key={inv.id}
              invoice={inv}
              onSaved={load}
            />
          ))}
        </div>
      )}
    </div>
  );
}

/* ============================================================
   CURRENCY INPUT
   ============================================================ */

function CurrencyInput({
  value,
  onChange,
  placeholder = "0.00",
  className = "",
}) {
  return (
    <div
      className={`flex overflow-hidden rounded-lg border border-line bg-surface focus-within:ring-2 focus-within:ring-teal-500/20 focus-within:border-teal-500 ${className}`}
    >
      <div className="flex items-center px-3 bg-surface-alt border-r border-line text-sm font-semibold text-ink/60 shrink-0">
        MWK
      </div>

      <Input
        type="number"
        min="0"
        step="0.01"
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        className="border-0 rounded-none focus:ring-0 focus:border-0"
      />
    </div>
  );
}

/* ============================================================
   NEW INVOICE
   ============================================================ */

function NewInvoicePanel({ onSaved }) {
  const [encounterId, setEncounterId] =
    useState(null);

  const [payerType, setPayerType] =
    useState("cash");

  const [items, setItems] = useState([
    {
      service_category: "consultation",
      description: "",
      amount: "",
    },
  ]);

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(null);
  const [resetKey, setResetKey] = useState(0);

  function updateItem(i, field, value) {
    setItems((list) =>
      list.map((it, idx) =>
        idx === i
          ? {
              ...it,
              [field]: value,
            }
          : it
      )
    );
  }

  function addItem() {
    setItems((list) => [
      ...list,
      {
        service_category: "laboratory",
        description: "",
        amount: "",
      },
    ]);
  }

  function removeItem(i) {
    setItems((list) =>
      list.length > 1
        ? list.filter(
            (_, idx) => idx !== i
          )
        : list
    );
  }

  async function submit(e) {
    e.preventDefault();

    setError("");
    setSuccess(null);

    if (!encounterId) {
      setError(
        "Search for and select the patient's visit before creating the invoice."
      );
      return;
    }

    const validItems = items.filter(
      (i) =>
        i.amount &&
        Number(i.amount) > 0
    );

    if (validItems.length === 0) {
      setError(
        "Add at least one line item with an amount greater than zero."
      );
      return;
    }

    setSaving(true);

    try {
      const res = await api.post(
        "/billing/invoices",
        {
          encounter_id: encounterId,
          payer_type: payerType,
          line_items: validItems.map(
            (i) => ({
              ...i,
              amount: Number(i.amount),
            })
          ),
        }
      );

      setSuccess(
        res.data.invoice_number
      );

      setEncounterId(null);

      setItems([
        {
          service_category:
            "consultation",
          description: "",
          amount: "",
        },
      ]);

      setResetKey((k) => k + 1);

      onSaved();
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Couldn't create the invoice — please check the details and try again."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">
        Create invoice
      </p>

      <form
        onSubmit={submit}
        className="space-y-3"
      >
        <div className="grid md:grid-cols-2 gap-3">
          <PatientLookup
            key={resetKey}
            requireEncounter
            label="Patient"
            onSelect={({ encounterId }) =>
              setEncounterId(
                encounterId
              )
            }
          />

          <Field label="Payer type">
            <Select
              value={payerType}
              onChange={(e) =>
                setPayerType(
                  e.target.value
                )
              }
            >
              <option value="cash">
                Cash
              </option>

              <option value="insurance">
                Insurance
              </option>

              <option value="institutional">
                Institutional
              </option>

              <option value="waiver">
                Waiver
              </option>
            </Select>
          </Field>
        </div>

        {items.map((item, i) => (
          <div
            key={i}
            className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center"
          >
            <Select
              value={
                item.service_category
              }
              onChange={(e) =>
                updateItem(
                  i,
                  "service_category",
                  e.target.value
                )
              }
            >
              {CATEGORIES.map((c) => (
                <option
                  key={c}
                  value={c}
                >
                  {c}
                </option>
              ))}
            </Select>

            <Input
              placeholder="Description"
              value={item.description}
              onChange={(e) =>
                updateItem(
                  i,
                  "description",
                  e.target.value
                )
              }
            />

            {/* FIXED MWK CURRENCY */}
            <CurrencyInput
              value={item.amount}
              onChange={(e) =>
                updateItem(
                  i,
                  "amount",
                  e.target.value
                )
              }
            />

            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() =>
                removeItem(i)
              }
              aria-label="Remove line item"
            >
              ✕
            </Button>
          </div>
        ))}

        <div className="flex gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={addItem}
          >
            + Add line item
          </Button>

          <Button
            type="submit"
            disabled={saving}
          >
            {saving
              ? "Creating…"
              : "Create invoice"}
          </Button>
        </div>

        {error && (
          <p
            role="alert"
            className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2"
          >
            {error}
          </p>
        )}

        {success && (
          <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2">
            Invoice {success} created.
          </p>
        )}
      </form>
    </Card>
  );
}

/* ============================================================
   INVOICE ROW
   ============================================================ */

function InvoiceRow({
  invoice,
  onSaved,
}) {
  const [amount, setAmount] =
    useState("");

  async function pay() {
    if (!amount) return;

    await api.post(
      `/billing/invoices/${invoice.id}/pay`,
      {
        amount: Number(amount),
      }
    );

    setAmount("");

    onSaved();
  }

  async function waive() {
    await api.post(
      `/billing/invoices/${invoice.id}/waive`
    );

    onSaved();
  }

  return (
    <Card>
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <p className="font-semibold mrn-mono">
            {invoice.invoice_number}
          </p>

          <p className="text-xs text-ink/50">
            {invoice.payer_type}

            {invoice.payer_name
              ? ` — ${invoice.payer_name}`
              : ""}
          </p>
        </div>

        <Badge
          tone={
            invoice.status === "paid"
              ? "success"
              : invoice.status === "waived"
              ? "muted"
              : "warning"
          }
        >
          {invoice.status}
        </Badge>
      </div>

      <p className="text-sm mt-2">
        Total: MWK{" "}
        {invoice.total_amount.toLocaleString()}{" "}
        · Paid: MWK{" "}
        {invoice.amount_paid.toLocaleString()}
      </p>

      {invoice.status !== "paid" &&
        invoice.status !== "waived" && (
          <div className="flex gap-2 mt-2">
            {/* FIXED MWK CURRENCY */}
            <CurrencyInput
              className="w-40"
              value={amount}
              onChange={(e) =>
                setAmount(
                  e.target.value
                )
              }
            />

            <Button
              size="sm"
              onClick={pay}
            >
              Record payment
            </Button>

            <Button
              size="sm"
              variant="ghost"
              onClick={waive}
            >
              Waive
            </Button>
          </div>
        )}
    </Card>
  );
}