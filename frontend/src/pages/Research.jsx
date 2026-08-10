import { useEffect, useState } from "react";
import { FileSearch, Download, CheckCircle2 } from "lucide-react";
import api from "../services/api";
import { Card, Button, Field, Input, Select, LoadingRow, EmptyState } from "../components/ui";
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

      {/* Report export section: date-range, filters, generate and export */}
      <Card>
        <h3 className="font-semibold mb-3">Hospital report export</h3>
        <p className="text-sm text-ink/60 mb-3">Generate aggregated hospital statistics for a date range and export CSV-ready datasets.</p>
        <ReportExportForm />
      </Card>
    </div>
  );
}

function ReportExportForm() {
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [preset, setPreset] = useState("");
  const [deidentify, setDeidentify] = useState(true);
  const [filters, setFilters] = useState({ age: "", sex: "", diagnosis: "", icd: "", laboratory: "", imaging: "", treatment: "", admission: "" });

  const [report, setReport] = useState(null);
  const [reportLoading, setReportLoading] = useState(false);
  const [reportError, setReportError] = useState("");
  const [exportLoading, setExportLoading] = useState(false);
  const [exported, setExported] = useState(false);

  function applyPreset(p) {
    const today = new Date();
    let s = "";
    let e = "";
    if (p === "today") {
      s = e = today.toISOString().slice(0, 10);
    } else if (p === "week") {
      const first = new Date(today);
      first.setDate(today.getDate() - today.getDay());
      s = first.toISOString().slice(0, 10);
      e = today.toISOString().slice(0, 10);
    } else if (p === "month") {
      const first = new Date(today.getFullYear(), today.getMonth(), 1);
      s = first.toISOString().slice(0, 10);
      e = today.toISOString().slice(0, 10);
    } else if (p === "year") {
      const first = new Date(today.getFullYear(), 0, 1);
      s = first.toISOString().slice(0, 10);
      e = today.toISOString().slice(0, 10);
    }
    setPreset(p);
    setStartDate(s);
    setEndDate(e);
  }

  async function generateReport(e) {
    if (e && e.preventDefault) e.preventDefault();
    setReportLoading(true);
    setReportError("");
    setReport(null);
    try {
      const params = { start_date: startDate, end_date: endDate, deidentify, ...filters };
      const res = await api.get("/research/report-summary", { params });
      setReport(res.data);
    } catch (err) {
      setReportError(err.response?.data?.message || "Couldn't generate report — backend may not support this endpoint yet.");
    } finally {
      setReportLoading(false);
    }
  }

  async function exportReport() {
    setExportLoading(true);
    setExported(false);
    setReportError("");
    try {
      const params = { start_date: startDate, end_date: endDate, deidentify, ...filters };
      const res = await api.get("/research/report-export", { params, responseType: "blob" });
      const url = URL.createObjectURL(new Blob([res.data], { type: "text/csv" }));
      const a = document.createElement("a");
      a.href = url;
      a.download = `nullcare-report-${startDate || 'start'}-${endDate || 'end'}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      setExported(true);
    } catch (err) {
      setReportError(err.response?.data?.message || "Couldn't export report — try again.");
    } finally {
      setExportLoading(false);
    }
  }

  return (
    <div className="space-y-4">
      <form onSubmit={generateReport} className="grid md:grid-cols-4 gap-3 items-end">
        <Field label="From date"><Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} /></Field>
        <Field label="To date"><Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} /></Field>
        <div className="flex gap-2 items-center">
          <Button type="button" variant={preset === "today" ? "primary" : "secondary"} onClick={() => applyPreset("today")}>Today</Button>
          <Button type="button" variant={preset === "week" ? "primary" : "secondary"} onClick={() => applyPreset("week")}>This Week</Button>
          <Button type="button" variant={preset === "month" ? "primary" : "secondary"} onClick={() => applyPreset("month")}>This Month</Button>
          <Button type="button" variant={preset === "year" ? "primary" : "secondary"} onClick={() => applyPreset("year")}>This Year</Button>
        </div>
        <div className="flex gap-2">
          <Button type="submit">Generate report</Button>
          <Button type="button" variant="secondary" onClick={() => { setStartDate(""); setEndDate(""); setPreset(""); setReport(null); setReportError(""); }}>Reset</Button>
        </div>
      </form>

      <div className="grid md:grid-cols-4 gap-3">
        <Field label="Age"><Select value={filters.age} onChange={(e) => setFilters({ ...filters, age: e.target.value })}><option value="">All</option><option value="buckets">Age buckets</option></Select></Field>
        <Field label="Sex"><Select value={filters.sex} onChange={(e) => setFilters({ ...filters, sex: e.target.value })}><option value="">All</option><option value="male">Male</option><option value="female">Female</option></Select></Field>
        <Field label="Diagnosis / ICD"><Input value={filters.diagnosis} onChange={(e) => setFilters({ ...filters, diagnosis: e.target.value })} placeholder="Search diagnosis or ICD" /></Field>
        <div className="flex items-center gap-2 mt-2"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={deidentify} onChange={(e) => setDeidentify(e.target.checked)} /> De-identify patient data</label></div>
        <Field label="Laboratory"><Input value={filters.laboratory} onChange={(e) => setFilters({ ...filters, laboratory: e.target.value })} placeholder="e.g. CBC" /></Field>
        <Field label="Imaging"><Input value={filters.imaging} onChange={(e) => setFilters({ ...filters, imaging: e.target.value })} placeholder="e.g. X-ray" /></Field>
        <Field label="Treatment"><Input value={filters.treatment} onChange={(e) => setFilters({ ...filters, treatment: e.target.value })} placeholder="Medication or procedure" /></Field>
        <Field label="Admission"><Select value={filters.admission} onChange={(e) => setFilters({ ...filters, admission: e.target.value })}><option value="">Any</option><option value="admitted">Admitted</option><option value="discharged">Discharged</option></Select></Field>
      </div>

      {reportLoading && <LoadingRow label="Generating report…" />}

      {reportError && <p className="text-sm text-alert">{reportError}</p>}

      {!reportLoading && report && (
        <div className="space-y-3">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div className="p-3 bg-surface-alt rounded">Total patients<div className="font-display text-2xl">{report.total_patients ?? "—"}</div></div>
            <div className="p-3 bg-surface-alt rounded">New patients<div className="font-display text-2xl">{report.new_patients ?? "—"}</div></div>
            <div className="p-3 bg-surface-alt rounded">Admissions<div className="font-display text-2xl">{report.admissions ?? "—"}</div></div>
            <div className="p-3 bg-surface-alt rounded">Discharges<div className="font-display text-2xl">{report.discharges ?? "—"}</div></div>
          </div>

          <div className="grid md:grid-cols-2 gap-3">
            <Card>
              <p className="font-semibold">Department activity</p>
              {(report.patients_by_department || []).length === 0 ? <p className="text-sm text-ink/50 mt-2">No data</p> : (
                <div className="overflow-x-auto mt-2">
                  <table className="w-full text-sm">
                    <thead className="text-xs text-ink/50 uppercase"><tr><th className="text-left py-1">Department</th><th className="text-left py-1">Patients</th></tr></thead>
                    <tbody>
                      {(report.patients_by_department || []).map((d) => (
                        <tr key={d.department} className="border-t border-line"><td className="py-1.5">{d.department || '(unknown)'}</td><td className="py-1.5">{d.count}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>

            <Card>
              <p className="font-semibold">Top diagnoses</p>
              {(report.top_diagnoses || []).length === 0 ? <p className="text-sm text-ink/50 mt-2">No data</p> : (
                <div className="overflow-x-auto mt-2">
                  <table className="w-full text-sm">
                    <thead className="text-xs text-ink/50 uppercase"><tr><th className="text-left py-1">Diagnosis / ICD</th><th className="text-left py-1">Count</th></tr></thead>
                    <tbody>
                      {(report.top_diagnoses || []).map((t, idx) => (
                        <tr key={idx} className="border-t border-line"><td className="py-1.5">{t.diagnosis || t.code || '—'}</td><td className="py-1.5">{t.count}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>
          </div>

          <div className="flex gap-2">
            <Button onClick={exportReport} disabled={exportLoading}>{exportLoading ? "Exporting…" : "Export CSV"}</Button>
            {exported && <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2 mt-0 flex items-center gap-1.5"><CheckCircle2 size={14} /> Exported.</p>}
          </div>
        </div>
      )}

      {!reportLoading && !report && !reportError && (
        <EmptyState title="No report generated" hint="Choose a date range and click Generate report to produce hospital statistics." />
      )}
    </div>
  );
}
