import { useEffect, useState, useCallback } from "react";
import { Pill } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { useAuth } from "../context/AuthContext";

export default function Pharmacy() {
  const { hasRole } = useAuth();
  const [prescriptions, setPrescriptions] = useState([]);
  const [stock, setStock] = useState([]);
  const [tab, setTab] = useState("pending");
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/pharmacy/prescriptions", { params: { status: tab } }),
      api.get("/pharmacy/stock"),
    ])
      .then(([pRes, sRes]) => { setPrescriptions(pRes.data); setStock(sRes.data); })
      .catch(() => { setPrescriptions([]); setStock([]); })
      .finally(() => setLoading(false));
  }, [tab]);

  useEffect(() => { load(); }, [load]);

  async function dispense(id) {
    await api.post(`/pharmacy/prescriptions/${id}/dispense`);
    load();
  }

  const lowStock = stock.filter((s) => s.low_stock);

  return (
    <div className="space-y-5">
      <PageHeader icon={Pill} title="Pharmacy" subtitle="Prescriptions with automated allergy, interaction, pediatric, and renal safety checks." />

      {lowStock.length > 0 && (
        <Card className="border-clay/30 bg-clay/5">
          <p className="font-semibold text-clay">Low stock alert</p>
          <p className="text-sm mt-1">{lowStock.map((s) => s.drug_name).join(", ")} at or below reorder level.</p>
        </Card>
      )}

      <div className="flex gap-2">
        {["pending", "dispensed"].map((s) => (
          <button
            key={s}
            onClick={() => setTab(s)}
            className={`px-3 py-1.5 rounded-full text-sm font-medium capitalize ${
              tab === s ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"
            }`}
          >
            {s}
          </button>
        ))}
      </div>

      {loading ? <LoadingRow /> : prescriptions.length === 0 ? (
        <EmptyState title={`No ${tab} prescriptions`} />
      ) : (
        <div className="space-y-3">
          {prescriptions.map((rx) => (
            <Card key={rx.id}>
              <div className="flex items-center justify-between flex-wrap gap-2">
                <div>
                  <p className="font-semibold">{rx.drug_name} {rx.formulation}</p>
                  <p className="text-xs text-ink/50">{rx.dose} · {rx.route} · {rx.frequency} · {rx.duration}</p>
                  {rx.is_pediatric_dose && <Badge tone="warning" className="mt-1">Pediatric dose — verify weight-based calculation</Badge>}
                </div>
                {hasRole("pharmacist") && rx.status === "pending" && (
                  <Button size="sm" onClick={() => dispense(rx.id)}>Dispense</Button>
                )}
                {rx.status === "dispensed" && <Badge tone="success">Dispensed</Badge>}
              </div>
              {rx.cds_alerts && JSON.parse(rx.cds_alerts).length > 0 && (
                <div className="mt-2 space-y-1">
                  {JSON.parse(rx.cds_alerts).map((alert, i) => (
                    <p key={i} className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">
                      {alert}
                    </p>
                  ))}
                </div>
              )}
            </Card>
          ))}
        </div>
      )}

      <Card>
        <p className="font-display text-lg mb-3">Stock levels</p>
        <div className="overflow-x-auto">
        <table className="w-full min-w-[420px] text-sm">
          <thead className="text-xs text-ink/50 uppercase">
            <tr><th className="text-left py-1">Drug</th><th className="text-left py-1">On hand</th><th className="text-left py-1">Reorder level</th><th></th></tr>
          </thead>
          <tbody>
            {stock.map((s) => (
              <tr key={s.id} className="border-t border-line">
                <td className="py-1.5">{s.drug_name}</td>
                <td className="py-1.5">{s.quantity_on_hand} {s.unit}</td>
                <td className="py-1.5">{s.reorder_level}</td>
                <td className="py-1.5">{s.low_stock && <Badge tone="warning">Low</Badge>}</td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
      </Card>
    </div>
  );
}
