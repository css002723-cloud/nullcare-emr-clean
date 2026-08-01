import { useEffect, useState } from "react";
import { FileSearch, Download, CheckCircle2 } from "lucide-react";
import api from "../services/api";
import { Card, Button, LoadingRow } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function Research() {
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(false);
  const [downloaded, setDownloaded] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    api.get("/research/consent-summary").then((res) => setSummary(res.data)).catch(() => {});
  }, []);

  async function exportAndDownload() {
    setLoading(true);
    setError("");
    setDownloaded(false);
    try {
      const res = await api.get("/research/export", { responseType: "blob" });
      const url = URL.createObjectURL(new Blob([res.data], { type: "text/csv" }));
      const a = document.createElement("a");
      a.href = url;
      a.download = `nullcare-research-export-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      setDownloaded(true);
    } catch {
      setError("Couldn't generate the export — please try again.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-5 max-w-2xl">
      <PageHeader
        icon={FileSearch}
        title="De-identified research export"
        subtitle="Exports include only patients who gave explicit consent for research use. Direct identifiers (name, permanent patient ID, national ID, phone, guardian details, village/TA) are removed; age is bucketed and location generalized to district/region to reduce re-identification risk."
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
        <p className="text-sm text-ink/60 mb-3">
          Generates a CSV file — one row per patient visit, patient-level fields repeated — ready for Excel, Stata,
          R, or DHIS2 import.
        </p>
        <Button onClick={exportAndDownload} disabled={loading} icon={Download}>
          {loading ? "Preparing CSV…" : "Download de-identified CSV"}
        </Button>
        {downloaded && (
          <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2 mt-3 flex items-center gap-1.5">
            <CheckCircle2 size={14} /> CSV downloaded.
          </p>
        )}
        {error && <p className="text-sm text-alert mt-3">{error}</p>}
      </Card>
    </div>
  );
}
