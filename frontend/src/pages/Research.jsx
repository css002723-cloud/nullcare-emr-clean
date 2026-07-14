import { useEffect, useState } from "react";
import { FileSearch, Download } from "lucide-react";
import api from "../services/api";
import { Card, Button, LoadingRow } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function Research() {
  const [summary, setSummary] = useState(null);
  const [dataset, setDataset] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    api.get("/research/consent-summary").then((res) => setSummary(res.data)).catch(() => {});
  }, []);

  async function exportData() {
    setLoading(true);
    try {
      const res = await api.get("/research/export");
      setDataset(res.data);
    } finally {
      setLoading(false);
    }
  }

  function download() {
    const blob = new Blob([JSON.stringify(dataset, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `nullcare_research_export_${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-5 max-w-2xl">
      <PageHeader
        icon={FileSearch}
        title="De-identified research export"
        subtitle="Exports include only patients who gave explicit consent for research use. Direct identifiers (name, MRN, national ID, phone, guardian details, village/TA) are removed; age is bucketed and location generalized to district/region to reduce re-identification risk."
      />

      {summary && (
        <Card>
          <p className="text-sm text-ink/60">Consent rate</p>
          <p className="font-display text-2xl">{summary.consent_rate_pct}%</p>
          <p className="text-xs text-ink/50 mt-1">
            {summary.consented_for_research} of {summary.total_patients} patients have consented to research use of their data.
          </p>
        </Card>
      )}

      <Card>
        <Button onClick={exportData} disabled={loading}>{loading ? "Preparing export…" : "Generate de-identified dataset"}</Button>
        {dataset && (
          <div className="mt-4">
            <p className="text-sm">{dataset.record_count} records ready.</p>
            <p className="text-xs text-ink/50 mt-1">{dataset.note}</p>
            <Button variant="secondary" size="sm" className="mt-2" onClick={download} icon={Download}>Download JSON</Button>
          </div>
        )}
      </Card>
    </div>
  );
}
