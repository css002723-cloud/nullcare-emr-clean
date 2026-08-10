import { useEffect, useState, useCallback } from "react";
import {
  Receipt,
  Plus,
  Trash2,
  CreditCard,
  Wallet,
  FileText,
  AlertCircle,
  CheckCircle2,
  Clock3,
  ChevronRight,
  RefreshCw,
} from "lucide-react";

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

/* ============================================================
   CONSTANTS
   ============================================================ */

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

const CATEGORY_LABELS = {
  consultation: "Consultation",
  laboratory: "Laboratory",
  imaging: "Imaging",
  pharmacy: "Pharmacy",
  procedure: "Procedure",
  theatre: "Theatre",
  admission: "Admission",
  bed: "Bed",
  consumables: "Consumables",
};

/* ============================================================
   MAIN BILLING PAGE
   ============================================================ */

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

  const paidInvoices = invoices.filter(
    (invoice) => invoice.status === "paid"
  ).length;

  const pendingInvoices = invoices.filter(
    (invoice) =>
      invoice.status !== "paid" &&
      invoice.status !== "waived"
  ).length;

  return (
    <div className="space-y-6 pb-10">

      {/* ======================================================
          HEADER
          ====================================================== */}

      <PageHeader
        icon={Receipt}
        title="Billing & revenue cycle"
        subtitle="Manage invoices, payments, outstanding balances, and patient charges."
      />

      {/* ======================================================
          SUMMARY CARDS
          ====================================================== */}

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        {/* Outstanding */}
        <div className="relative overflow-hidden rounded-2xl border border-teal-500/15 bg-gradient-to-br from-teal-500/[0.08] to-transparent p-5">
          <div className="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-teal-500/10" />

          <div className="relative flex items-start justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-ink/50">
                Outstanding
              </p>

              <p className="mt-2 text-2xl font-bold tracking-tight text-ink">
                MWK{" "}
                {Number(
                  report?.outstanding_total || 0
                ).toLocaleString()}
              </p>

              <p className="mt-1 text-xs text-ink/45">
                {report?.count || 0} unpaid invoice
                {report?.count === 1 ? "" : "s"}
              </p>
            </div>

            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/10 text-teal-700 dark:text-teal-300">
              <Wallet size={19} />
            </div>
          </div>
        </div>

        {/* Total invoices */}
        <div className="rounded-2xl border border-line bg-surface p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-ink/50">
                Total invoices
              </p>

              <p className="mt-2 text-2xl font-bold tracking-tight">
                {invoices.length}
              </p>

              <p className="mt-1 text-xs text-ink/45">
                All billing records
              </p>
            </div>

            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-alt text-ink/60">
              <FileText size={19} />
            </div>
          </div>
        </div>

        {/* Pending */}
        <div className="rounded-2xl border border-line bg-surface p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-ink/50">
                Pending
              </p>

              <p className="mt-2 text-2xl font-bold tracking-tight">
                {pendingInvoices}
              </p>

              <p className="mt-1 text-xs text-ink/45">
                Awaiting payment
              </p>
            </div>

            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
              <Clock3 size={19} />
            </div>
          </div>
        </div>

        {/* Paid */}
        <div className="rounded-2xl border border-line bg-surface p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-ink/50">
                Paid
              </p>

              <p className="mt-2 text-2xl font-bold tracking-tight">
                {paidInvoices}
              </p>

              <p className="mt-1 text-xs text-ink/45">
                Completed invoices
              </p>
            </div>

            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
              <CheckCircle2 size={19} />
            </div>
          </div>
        </div>
      </div>

      {/* ======================================================
          CREATE INVOICE
          ====================================================== */}

      <NewInvoicePanel onSaved={load} />

      {/* ======================================================
          INVOICE LIST HEADER
          ====================================================== */}

      <div className="flex items-center justify-between gap-3">

        <div>
          <h2 className="text-lg font-bold tracking-tight">
            Recent invoices
          </h2>

          <p className="text-sm text-ink/50 mt-0.5">
            Review invoices and record patient payments.
          </p>
        </div>

        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={load}
          disabled={loading}
          className="gap-2"
        >
          <RefreshCw
            size={15}
            className={loading ? "animate-spin" : ""}
          />
          Refresh
        </Button>
      </div>

      {/* ======================================================
          INVOICES
          ====================================================== */}

      {loading ? (
        <div className="rounded-2xl border border-line bg-surface p-6">
          <LoadingRow />
        </div>
      ) : invoices.length === 0 ? (
        <Card className="rounded-2xl">
          <EmptyState
            title="No invoices yet"
            description="Invoices created for patients will appear here."
          />
        </Card>
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
      className={`
        flex overflow-hidden rounded-xl
        border border-line
        bg-surface
        transition-all
        focus-within:border-teal-500
        focus-within:ring-4
        focus-within:ring-teal-500/10
        ${className}
      `}
    >
      <div className="flex items-center px-3 bg-surface-alt border-r border-line text-xs font-bold text-ink/50 shrink-0">
        MWK
      </div>

      <Input
        type="number"
        min="0"
        step="0.01"
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        className="flex-1 min-w-0 border-0 rounded-none focus:ring-0 focus:border-0"
      />
    </div>
  );
}

/* ============================================================
   NEW INVOICE PANEL
   ============================================================ */

function NewInvoicePanel({ onSaved }) {
  const [encounterId, setEncounterId] = useState(null);

  const [payerType, setPayerType] = useState("cash");

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

  const [pendingCharges, setPendingCharges] = useState([]);
  const [loadingCharges, setLoadingCharges] = useState(false);
  const [chargesUnavailable, setChargesUnavailable] =
    useState(false);

  function loadPendingCharges(patientId) {
    setPendingCharges([]);
    setChargesUnavailable(false);

    if (!patientId) return;

    setLoadingCharges(true);

    api
      .get(`/billing/patients/${patientId}/pending-charges`)
      .then((res) => {
        setPendingCharges(res.data.charges || []);
      })
      .catch(() => {
        setPendingCharges([]);
        setChargesUnavailable(true);
      })
      .finally(() => {
        setLoadingCharges(false);
      });
  }

  function addChargeAsLineItem(charge) {
    setItems((list) => {
      const blank =
        list.length === 1 &&
        !list[0].description &&
        !list[0].amount;

      const newItem = {
        service_category: charge.service_category,
        description: charge.description,
        amount: String(charge.amount),
        chargeable_type: charge.chargeable_type,
        chargeable_id: charge.chargeable_id,
      };

      return blank
        ? [newItem]
        : [...list, newItem];
    });

    setPendingCharges((list) =>
      list.filter(
        (c) =>
          !(
            c.chargeable_type ===
              charge.chargeable_type &&
            c.chargeable_id ===
              charge.chargeable_id
          )
      )
    );
  }

  function addAllPendingCharges() {
    const charges = [...pendingCharges];

    charges.forEach((charge) => {
      addChargeAsLineItem(charge);
    });
  }

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
        ? list.filter((_, idx) => idx !== i)
        : list
    );
  }

  const total = items.reduce(
    (sum, item) =>
      sum + (Number(item.amount) || 0),
    0
  );

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
          line_items: validItems.map((i) => ({
            service_category:
              i.service_category,
            description: i.description,
            amount: Number(i.amount),
            chargeable_type:
              i.chargeable_type || null,
            chargeable_id:
              i.chargeable_id || null,
          })),
        }
      );

      setSuccess(res.data.invoice_number);

      setEncounterId(null);

      setItems([
        {
          service_category: "consultation",
          description: "",
          amount: "",
        },
      ]);

      setPendingCharges([]);
      setChargesUnavailable(false);
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
    <div className="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">

      {/* Header */}
      <div className="border-b border-line bg-gradient-to-r from-teal-500/[0.06] to-transparent px-5 py-4 sm:px-6">
        <div className="flex items-center gap-3">

          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/10 text-teal-700 dark:text-teal-300">
            <Receipt size={19} />
          </div>

          <div>
            <h2 className="font-bold text-base">
              Create invoice
            </h2>

            <p className="text-xs text-ink/50 mt-0.5">
              Create a new patient invoice from services provided.
            </p>
          </div>
        </div>
      </div>

      {/* Form */}
      <form
        onSubmit={submit}
        className="p-5 sm:p-6 space-y-6"
      >

        {/* Patient + Payer */}
        <div className="grid md:grid-cols-[1fr_220px] gap-4">

          <Field label="Patient">
            <PatientLookup
              key={resetKey}
              requireEncounter
              label="Search patient or visit"
              onSelect={({ patientId, encounterId }) => {
                setEncounterId(encounterId);
                loadPendingCharges(patientId);
              }}
            />
          </Field>

          <Field label="Payer type">
            <Select
              value={payerType}
              onChange={(e) =>
                setPayerType(e.target.value)
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

        {/* Loading */}
        {loadingCharges && (
          <div className="flex items-center gap-2 rounded-xl bg-surface-alt px-4 py-3 text-xs text-ink/50">
            <RefreshCw
              size={14}
              className="animate-spin"
            />
            Checking for unbilled charges…
          </div>
        )}

        {/* Pending charges */}
        {!loadingCharges &&
          pendingCharges.length > 0 && (
            <div className="rounded-2xl border border-teal-500/20 bg-teal-500/[0.04] overflow-hidden">

              <div className="flex items-center justify-between gap-3 px-4 py-3 border-b border-teal-500/10">

                <div className="flex items-center gap-2">
                  <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-500/10 text-teal-700 dark:text-teal-300">
                    <AlertCircle size={14} />
                  </div>

                  <div>
                    <p className="text-sm font-semibold">
                      Unbilled charges
                    </p>

                    <p className="text-xs text-ink/50">
                      {pendingCharges.length} charge
                      {pendingCharges.length === 1
                        ? ""
                        : "s"} available
                    </p>
                  </div>
                </div>

                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={addAllPendingCharges}
                  className="gap-1.5"
                >
                  <Plus size={14} />
                  Add all
                </Button>
              </div>

              <div className="p-3 space-y-2">
                {pendingCharges.map((c) => (
                  <div
                    key={`${c.chargeable_type}-${c.chargeable_id}`}
                    className="
                      flex items-center justify-between gap-3
                      rounded-xl
                      bg-surface
                      border border-line/70
                      px-3 py-2.5
                      transition
                      hover:border-teal-500/20
                    "
                  >
                    <div className="min-w-0">

                      <p className="text-sm font-medium truncate">
                        {c.description}
                      </p>

                      <p className="text-[11px] text-ink/45 mt-0.5">
                        {CATEGORY_LABELS[
                          c.service_category
                        ] ||
                          c.service_category}
                      </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">

                      <span className="text-sm font-bold mrn-mono">
                        MWK{" "}
                        {Number(
                          c.amount
                        ).toLocaleString()}
                      </span>

                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                          addChargeAsLineItem(c)
                        }
                        className="gap-1"
                      >
                        <Plus size={13} />
                        Add
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

        {/* Fallback message */}
        {!loadingCharges &&
          chargesUnavailable && (
            <div className="flex items-start gap-2 rounded-xl bg-surface-alt px-4 py-3 text-xs text-ink/50">
              <AlertCircle
                size={14}
                className="mt-0.5 shrink-0"
              />
              <span>
                Couldn't check for unbilled charges automatically.
                You can add line items manually below.
              </span>
            </div>
          )}

        {/* Line items */}
        <div>

          <div className="flex items-center justify-between mb-3">

            <div>
              <p className="text-sm font-bold">
                Invoice items
              </p>

              <p className="text-xs text-ink/45 mt-0.5">
                Add the services and charges for this visit.
              </p>
            </div>

            <span className="text-xs font-semibold text-ink/40">
              {items.length} item
              {items.length === 1 ? "" : "s"}
            </span>
          </div>

          <div className="space-y-2">

            {/* Column labels */}
            <div className="hidden sm:grid sm:grid-cols-[170px_1fr_180px_40px] gap-2 px-2 text-[10px] font-bold uppercase tracking-wider text-ink/35">
              <span>Category</span>
              <span>Description</span>
              <span>Amount</span>
              <span />
            </div>

            {items.map((item, i) => (
              <div
                key={i}
                className="
                  grid
                  grid-cols-1
                  sm:grid-cols-[170px_1fr_180px_40px]
                  gap-2
                  rounded-xl
                  border border-line
                  bg-surface-alt/40
                  p-2
                  transition
                  focus-within:border-teal-500/30
                  focus-within:ring-2
                  focus-within:ring-teal-500/5
                "
              >

                <Select
                  value={item.service_category}
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
                      {CATEGORY_LABELS[c] || c}
                    </option>
                  ))}
                </Select>

                <Input
                  placeholder="Describe the service..."
                  value={item.description}
                  onChange={(e) =>
                    updateItem(
                      i,
                      "description",
                      e.target.value
                    )
                  }
                />

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

                <button
                  type="button"
                  onClick={() => removeItem(i)}
                  disabled={items.length === 1}
                  aria-label="Remove line item"
                  className="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    text-ink/35
                    transition
                    hover:bg-alert/10
                    hover:text-alert
                    disabled:opacity-30
                    disabled:hover:bg-transparent
                  "
                >
                  <Trash2 size={16} />
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Total */}
        <div className="flex items-center justify-between rounded-2xl border border-teal-500/15 bg-teal-500/[0.04] px-4 py-4">

          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-ink/45">
              Invoice total
            </p>

            <p className="text-xs text-ink/40 mt-0.5">
              Based on entered line items
            </p>
          </div>

          <p className="text-xl sm:text-2xl font-bold mrn-mono text-teal-700 dark:text-teal-300">
            MWK {total.toLocaleString()}
          </p>
        </div>

        {/* Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-2">

          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={addItem}
            className="gap-2"
          >
            <Plus size={15} />
            Add line item
          </Button>

          <Button
            type="submit"
            disabled={saving}
            className="sm:ml-auto gap-2"
          >
            <Receipt size={15} />

            {saving
              ? "Creating…"
              : "Create invoice"}
          </Button>
        </div>

        {/* Error */}
        {error && (
          <div
            role="alert"
            className="flex items-start gap-2 text-sm text-alert bg-alert/5 border border-alert/20 rounded-xl px-4 py-3"
          >
            <AlertCircle
              size={16}
              className="mt-0.5 shrink-0"
            />

            <span>{error}</span>
          </div>
        )}

        {/* Success */}
        {success && (
          <div className="flex items-start gap-2 text-sm text-moss bg-moss/10 border border-moss/20 rounded-xl px-4 py-3">
            <CheckCircle2
              size={16}
              className="mt-0.5 shrink-0"
            />

            <span>
              Invoice{" "}
              <strong>{success}</strong>{" "}
              created successfully.
            </span>
          </div>
        )}
      </form>
    </div>
  );
}

/* ============================================================
   INVOICE ROW
   ============================================================ */

function InvoiceRow({
  invoice,
  onSaved,
}) {
  const [amount, setAmount] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function pay() {
    if (!amount) return;

    setError("");
    setSaving(true);

    try {
      await api.post(
        `/billing/invoices/${invoice.id}/pay`,
        {
          amount: Number(amount),
        }
      );

      setAmount("");
      onSaved();
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Payment wasn't recorded — please try again before assuming it went through."
      );
    } finally {
      setSaving(false);
    }
  }

  async function waive() {
    setError("");
    setSaving(true);

    try {
      await api.post(
        `/billing/invoices/${invoice.id}/waive`
      );

      onSaved();
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Couldn't waive this invoice — please try again."
      );
    } finally {
      setSaving(false);
    }
  }

  const total = Number(invoice.total_amount || 0);
  const paid = Number(invoice.amount_paid || 0);
  const remaining = Math.max(total - paid, 0);

  const progress =
    total > 0
      ? Math.min((paid / total) * 100, 100)
      : 0;

  const isPaid = invoice.status === "paid";
  const isWaived = invoice.status === "waived";

  return (
    <div className="group rounded-2xl border border-line bg-surface shadow-sm transition-all duration-200 hover:-translate-y-[1px] hover:shadow-md">

      <div className="p-4 sm:p-5">

        {/* Top */}
        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">

          <div className="flex items-start gap-3 min-w-0">

            <div
              className={`
                flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                ${
                  isPaid
                    ? "bg-emerald-500/10 text-emerald-600"
                    : isWaived
                    ? "bg-surface-alt text-ink/45"
                    : "bg-teal-500/10 text-teal-700 dark:text-teal-300"
                }
              `}
            >
              {isPaid ? (
                <CheckCircle2 size={18} />
              ) : isWaived ? (
                <FileText size={18} />
              ) : (
                <Receipt size={18} />
              )}
            </div>

            <div className="min-w-0">

              <div className="flex flex-wrap items-center gap-2">

                <p className="font-bold mrn-mono text-sm">
                  {invoice.invoice_number}
                </p>

                <Badge
                  tone={
                    isPaid
                      ? "success"
                      : isWaived
                      ? "muted"
                      : "warning"
                  }
                >
                  {invoice.status}
                </Badge>
              </div>

              {invoice.patient_name && (
                <p className="text-sm text-ink/70 mt-1">
                  {invoice.patient_name}
                  <span className="mx-1.5 text-ink/25">
                    •
                  </span>
                  <span className="mrn-mono text-xs">
                    {invoice.patient_uid}
                  </span>
                </p>
              )}

              <p className="text-xs text-ink/45 mt-1 capitalize">
                {invoice.payer_type}

                {invoice.payer_name
                  ? ` — ${invoice.payer_name}`
                  : ""}
              </p>
            </div>
          </div>

          {/* Amount */}
          <div className="sm:text-right shrink-0">

            <p className="text-[10px] uppercase tracking-wider font-bold text-ink/35">
              Balance
            </p>

            <p
              className={`
                text-lg font-bold mrn-mono
                ${
                  remaining > 0
                    ? "text-teal-700 dark:text-teal-300"
                    : "text-emerald-600"
                }
              `}
            >
              MWK {remaining.toLocaleString()}
            </p>
          </div>
        </div>

        {/* Payment progress */}
        <div className="mt-5">

          <div className="flex items-center justify-between text-xs mb-2">

            <span className="text-ink/50">
              Payment progress
            </span>

            <span className="font-semibold text-ink/60">
              {progress.toFixed(0)}%
            </span>
          </div>

          <div className="h-1.5 rounded-full bg-surface-alt overflow-hidden">

            <div
              className="
                h-full rounded-full
                bg-teal-500
                transition-all duration-500
              "
              style={{
                width: `${progress}%`,
              }}
            />
          </div>

          <div className="flex justify-between mt-2 text-[11px] text-ink/40">
            <span>
              Paid:{" "}
              <strong className="text-ink/55">
                MWK {paid.toLocaleString()}
              </strong>
            </span>

            <span>
              Total:{" "}
              <strong className="text-ink/55">
                MWK {total.toLocaleString()}
              </strong>
            </span>
          </div>
        </div>

        {/* Payment controls */}
        {!isPaid && !isWaived && (
          <div className="mt-5 pt-4 border-t border-line">

            <div className="flex flex-col sm:flex-row gap-2">

              <CurrencyInput
                className="sm:w-52"
                value={amount}
                onChange={(e) =>
                  setAmount(e.target.value)
                }
              />

              <Button
                size="sm"
                onClick={pay}
                disabled={
                  saving ||
                  !amount ||
                  Number(amount) <= 0
                }
                className="gap-2"
              >
                <CreditCard size={14} />

                {saving
                  ? "Recording…"
                  : "Record payment"}
              </Button>

              <Button
                size="sm"
                variant="ghost"
                onClick={waive}
                disabled={saving}
                className="sm:ml-auto"
              >
                Waive invoice
                <ChevronRight size={14} />
              </Button>
            </div>

            {error && (
              <div className="flex items-start gap-2 text-xs text-alert bg-alert/5 border border-alert/20 rounded-xl px-3 py-2 mt-3">
                <AlertCircle
                  size={14}
                  className="mt-0.5 shrink-0"
                />

                <span>{error}</span>
              </div>
            )}
          </div>
        )}

        {/* Completed state */}
        {isPaid && (
          <div className="mt-4 flex items-center gap-2 rounded-xl bg-emerald-500/5 border border-emerald-500/10 px-3 py-2.5 text-xs text-emerald-700 dark:text-emerald-300">
            <CheckCircle2 size={14} />
            Invoice fully paid.
          </div>
        )}

        {isWaived && (
          <div className="mt-4 flex items-center gap-2 rounded-xl bg-surface-alt px-3 py-2.5 text-xs text-ink/50">
            <FileText size={14} />
            This invoice has been waived.
          </div>
        )}
      </div>
    </div>
  );
}