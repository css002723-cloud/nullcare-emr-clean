import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import {
  ClipboardList,
  UserPlus,
  UserCheck,
  ListOrdered,
  Siren,
  X,
  Clock,
  ShieldCheck,
  User,
  MapPin,
  Stethoscope,
  Search,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  RefreshCw,
} from "lucide-react";
import api from "../services/api";
import {
  Card,
  Field,
  Input,
  Select,
  Button,
  Badge,
  Textarea,
  LoadingRow,
  EmptyState,
} from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";
import { createWithOfflineFallback } from "../offline/offlineResource";

const DISTRICTS = [
  "Blantyre",
  "Lilongwe",
  "Mzuzu",
  "Zomba",
  "Kasungu",
  "Mangochi",
  "Matching",
  "Other",
];

const REGIONS = ["Southern", "Central", "Northern"];

const TABS = [
  { value: "new", label: "New Patient", icon: UserPlus },
  { value: "returning", label: "Returning Patient", icon: UserCheck },
  { value: "queue", label: "Live Waiting Queue", icon: ListOrdered },
];

export default function Reception() {
  const [tab, setTab] = useState("new");

  return (
    <div className="space-y-6 w-full min-w-full pb-10">
      {/* Header */}
      <PageHeader
        icon={ClipboardList}
        title="Reception & Intake"
        subtitle="Register incoming arrivals, reactivate returning patients, and oversee real-time queue flow."
      />

      {/* Tab Switcher */}
      <div className="flex p-1 bg-surface-muted/80 backdrop-blur border border-line rounded-xl w-fit gap-1 shadow-sm">
        {TABS.map((t) => {
          const Icon = t.icon;
          const isActive = tab === t.value;
          return (
            <button
              key={t.value}
              onClick={() => setTab(t.value)}
              className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-150 ${
                isActive
                  ? "bg-teal-600 text-white shadow-sm font-semibold"
                  : "text-ink/60 hover:text-ink hover:bg-surface/60"
              }`}
            >
              <Icon size={16} className={isActive ? "text-white" : "text-ink/40"} />
              {t.label}
            </button>
          );
        })}
      </div>

      {/* Dynamic Content Container - Forced Full Width */}
      <div className="w-full min-w-full transition-all duration-200">
        {tab === "new" && <NewPatientPanel />}
        {tab === "returning" && <ReturningPatientPanel />}
        {tab === "queue" && <QueuePanel />}
      </div>
    </div>
  );
}

// ==================== NEW PATIENT ====================

const initialForm = {
  given_name: "",
  family_name: "",
  sex: "",
  date_of_birth: "",
  estimated_age: "",
  national_id: "",
  phone: "",
  village: "",
  traditional_authority: "",
  district: "",
  region: "",
  occupation: "",
  guardian_name: "",
  guardian_relationship: "",
  guardian_phone: "",
  patient_category: "outpatient",
  consent_research: false,
  chief_complaint: "",
  visit_type: "outpatient",
  priority: "routine",
};

function NewPatientPanel() {
  const navigate = useNavigate();
  const [form, setForm] = useState(initialForm);
  const [duplicates, setDuplicates] = useState([]);
  const [checking, setChecking] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState("");

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function checkDuplicates() {
    if (!form.given_name || !form.family_name) return;

    setChecking(true);
    try {
      const res = await api.post("/patients/check-duplicate", {
        given_name: form.given_name,
        family_name: form.family_name,
        national_id: form.national_id,
      });
      setDuplicates(res.data);
    } catch {
      setDuplicates([]);
    } finally {
      setChecking(false);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      const patientPayload = {
        given_name: form.given_name,
        family_name: form.family_name,
        sex: form.sex,
        date_of_birth: form.date_of_birth || undefined,
        estimated_age: form.estimated_age ? Number(form.estimated_age) : undefined,
        national_id: form.national_id,
        phone: form.phone,
        village: form.village,
        traditional_authority: form.traditional_authority,
        district: form.district,
        region: form.region,
        occupation: form.occupation,
        guardian_name: form.guardian_name,
        guardian_relationship: form.guardian_relationship,
        guardian_phone: form.guardian_phone,
        patient_category: form.patient_category,
        consent_research: form.consent_research,
      };

      const { data: patient, offline: patientOffline } = await createWithOfflineFallback(
        "patient",
        "/patients",
        patientPayload
      );

      const encounterPayload = {
        patient_id: patient.id,
        visit_type: form.visit_type,
        chief_complaint: form.chief_complaint,
        priority: form.priority,
      };

      if (patientOffline) {
        encounterPayload.patient_client_uuid = patient.client_uuid;
      }

      const { data: encounter, offline: encounterOffline } = await createWithOfflineFallback(
        "encounter",
        "/encounters",
        encounterPayload
      );

      setResult({
        patient,
        encounter,
        offline: patientOffline || encounterOffline,
      });

      setForm(initialForm);
      setDuplicates([]);
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Something went wrong while registering this patient."
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (result) {
    return (
      <Card className="w-full min-w-full max-w-full border-moss/40 bg-emerald-50/50 dark:bg-emerald-950/10 p-6 rounded-2xl space-y-4">
        <div className="flex items-center gap-3 text-emerald-600 dark:text-emerald-400">
          <CheckCircle2 size={24} className="shrink-0" />
          <h3 className="text-lg font-semibold">
            Registration Completed {result.offline ? " (Saved Offline)" : ""}
          </h3>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full bg-surface p-4 rounded-xl border border-line text-sm">
          <div>
            <span className="text-xs text-ink/50 block">Patient Name</span>
            <span className="font-semibold text-ink">
              {result.patient.given_name} {result.patient.family_name}
            </span>
          </div>
          <div>
            <span className="text-xs text-ink/50 block">Patient ID</span>
            <span className="font-mono font-medium text-teal-600">
              {result.offline ? "Pending Sync" : result.patient.patient_uid}
            </span>
          </div>
          <div>
            <span className="text-xs text-ink/50 block">Visit MRN</span>
            <span className="font-mono font-medium text-teal-600">
              {result.offline ? "Pending Sync" : result.encounter.mrn}
            </span>
          </div>
          <div>
            <span className="text-xs text-ink/50 block">Research Consent</span>
            <span className="font-medium text-ink">
              {result.patient.consent_research ? "Granted" : "Not Granted"}
            </span>
          </div>
        </div>

        {result.offline && (
          <p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-950/20 p-2.5 rounded-lg border border-amber-200/50">
            This record is held in offline storage and will receive official registration numbers automatically upon reconnection.
          </p>
        )}

        <div className="flex flex-wrap gap-3 pt-2">
          {!result.offline && (
            <Button
              size="sm"
              onClick={() => navigate(`/patients/${result.patient.patient_uid}`)}
              className="gap-2"
            >
              Open Record <ArrowRight size={14} />
            </Button>
          )}
          <Button size="sm" variant="secondary" onClick={() => setResult(null)}>
            Register Another Patient
          </Button>
        </div>
      </Card>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="w-full min-w-full max-w-full space-y-6">
      {/* Patient Identity Section */}
      <Card className="w-full p-6">
        <div className="flex items-center gap-2 mb-4 pb-2 border-b border-line text-ink/80">
          <User size={18} className="text-teal-600" />
          <h3 className="font-display font-semibold text-base">Patient Identity</h3>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
          <Field label="Given Name" required className="w-full">
            <Input
              className="w-full"
              value={form.given_name}
              onChange={(e) => update("given_name", e.target.value)}
              onBlur={checkDuplicates}
              placeholder="First name"
              required
            />
          </Field>

          <Field label="Family Name" required className="w-full">
            <Input
              className="w-full"
              value={form.family_name}
              onChange={(e) => update("family_name", e.target.value)}
              onBlur={checkDuplicates}
              placeholder="Last name"
              required
            />
          </Field>

          <Field label="Sex" className="w-full">
            <Select className="w-full" value={form.sex} onChange={(e) => update("sex", e.target.value)}>
              <option value="">Select…</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </Select>
          </Field>

          <Field label="Date of Birth" hint="Leave blank if using estimated age" className="w-full">
            <Input
              className="w-full"
              type="date"
              value={form.date_of_birth}
              onChange={(e) => update("date_of_birth", e.target.value)}
            />
          </Field>

          <Field label="Estimated Age (Years)" className="w-full">
            <Input
              className="w-full"
              type="number"
              min="0"
              max="120"
              placeholder="e.g. 34"
              value={form.estimated_age}
              onChange={(e) => update("estimated_age", e.target.value)}
            />
          </Field>

          <Field label="National ID" className="w-full">
            <Input
              className="w-full"
              value={form.national_id}
              onChange={(e) => update("national_id", e.target.value)}
              onBlur={checkDuplicates}
              placeholder="ID Number"
            />
          </Field>

          <Field label="Phone Number" className="w-full">
            <Input
              className="w-full"
              type="tel"
              value={form.phone}
              onChange={(e) => update("phone", e.target.value)}
              placeholder="+265..."
            />
          </Field>

          <Field label="Patient Category" className="w-full">
            <Select
              className="w-full"
              value={form.patient_category}
              onChange={(e) => update("patient_category", e.target.value)}
            >
              {[
                "outpatient",
                "inpatient",
                "student",
                "staff",
                "private",
                "referred",
                "emergency",
                "research",
              ].map((c) => (
                <option key={c} value={c}>
                  {c.charAt(0).toUpperCase() + c.slice(1)}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        {checking && (
          <div className="flex items-center gap-2 text-xs text-ink/50 mt-3 animate-pulse">
            <RefreshCw size={12} className="animate-spin" /> Checking duplicate records...
          </div>
        )}

        {duplicates.length > 0 && (
          <div className="mt-4 w-full bg-amber-50/80 dark:bg-amber-950/20 border border-amber-300/50 rounded-xl p-4 space-y-2">
            <div className="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-semibold text-sm">
              <AlertTriangle size={16} />
              Possible Duplicate Records Identified
            </div>

            <ul className="text-sm space-y-1.5 divide-y divide-amber-200/40 w-full">
              {duplicates.map((d) => (
                <li key={d.id} className="pt-1.5 flex items-center justify-between w-full">
                  <span>
                    <strong>{d.full_name}</strong> — <span className="font-mono text-xs">{d.patient_uid}</span>
                  </span>
                  <button
                    type="button"
                    className="text-xs font-semibold text-teal-600 hover:underline inline-flex items-center gap-1"
                    onClick={() => navigate(`/patients/${d.patient_uid}`)}
                  >
                    Open Record <ArrowRight size={12} />
                  </button>
                </li>
              ))}
            </ul>

            <p className="text-xs text-amber-700/70 pt-1">
              If this matches the patient, switch to the <strong>Returning Patient</strong> tab.
            </p>
          </div>
        )}
      </Card>

      {/* Location & Contact Section */}
      <Card className="w-full p-6">
        <div className="flex items-center gap-2 mb-4 pb-2 border-b border-line text-ink/80">
          <MapPin size={18} className="text-teal-600" />
          <h3 className="font-display font-semibold text-base">Location & Guardian Details</h3>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
          <Field label="Village / Locality" className="w-full">
            <Input
              className="w-full"
              value={form.village}
              onChange={(e) => update("village", e.target.value)}
            />
          </Field>

          <Field label="Traditional Authority (T/A)" className="w-full">
            <Input
              className="w-full"
              value={form.traditional_authority}
              onChange={(e) => update("traditional_authority", e.target.value)}
            />
          </Field>

          <Field label="District" className="w-full">
            <Select
              className="w-full"
              value={form.district}
              onChange={(e) => update("district", e.target.value)}
            >
              <option value="">Select District…</option>
              {DISTRICTS.map((d) => (
                <option key={d} value={d}>{d}</option>
              ))}
            </Select>
          </Field>

          <Field label="Region" className="w-full">
            <Select
              className="w-full"
              value={form.region}
              onChange={(e) => update("region", e.target.value)}
            >
              <option value="">Select Region…</option>
              {REGIONS.map((r) => (
                <option key={r} value={r}>{r}</option>
              ))}
            </Select>
          </Field>

          <Field label="Occupation / Workplace / School" className="w-full">
            <Input
              className="w-full"
              value={form.occupation}
              onChange={(e) => update("occupation", e.target.value)}
            />
          </Field>

          <Field label="Guardian / Next of Kin Name" className="w-full">
            <Input
              className="w-full"
              value={form.guardian_name}
              onChange={(e) => update("guardian_name", e.target.value)}
            />
          </Field>

          <Field label="Relationship to Patient" className="w-full">
            <Input
              className="w-full"
              value={form.guardian_relationship}
              onChange={(e) => update("guardian_relationship", e.target.value)}
            />
          </Field>

          <Field label="Guardian Contact Phone" className="w-full">
            <Input
              className="w-full"
              type="tel"
              value={form.guardian_phone}
              onChange={(e) => update("guardian_phone", e.target.value)}
            />
          </Field>
        </div>
      </Card>

      {/* Research Consent Component */}
      <ResearchConsentCard
        value={form.consent_research}
        onChange={(val) => update("consent_research", val)}
      />

      {/* Encounter / Visit Details removed for new patient registration per UX request */}

      {error && (
        <div className="p-3 rounded-lg bg-red-50 text-red-600 border border-red-200 text-sm flex items-center gap-2 w-full">
          <AlertTriangle size={16} />
          {error}
        </div>
      )}

      <div className="flex justify-end w-full">
        <Button type="submit" size="lg" disabled={submitting} className="min-w-[220px]">
          {submitting ? "Registering..." : "Register Patient & Open Visit"}
        </Button>
      </div>
    </form>
  );
}

// ==================== RESEARCH CONSENT ====================

function ResearchConsentCard({ value, onChange }) {
  return (
    <Card className="w-full p-5 bg-surface border-line">
      <div className="flex items-start gap-3.5 w-full">
        <div className="pt-1">
          <input
            id="consent-research"
            type="checkbox"
            checked={value}
            onChange={(e) => onChange(e.target.checked)}
            className="h-4 w-4 rounded border-line text-teal-600 focus:ring-teal-500 cursor-pointer"
          />
        </div>

        <div className="min-w-0 flex-1 w-full">
          <label
            htmlFor="consent-research"
            className="font-semibold text-sm cursor-pointer flex items-center gap-2 text-ink"
          >
            <ShieldCheck size={16} className="text-teal-600" />
            Research Data Consent
          </label>

          <p className="text-xs text-ink/60 mt-1 leading-relaxed">
            The patient agrees that de-identified clinical information may be utilized for authorized research, audit, and epidemiological monitoring purposes.
          </p>

          <div className="mt-3">
            <span
              className={`inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium border ${
                value
                  ? "bg-teal-50 text-teal-700 border-teal-200"
                  : "bg-surface-muted text-ink/50 border-line"
              }`}
            >
              {value ? "Consent Confirmed" : "Consent Not Provided"}
            </span>
          </div>
        </div>
      </div>
    </Card>
  );
}

// ==================== VISIT DETAILS ====================

function VisitDetailsCard({ form, update }) {
  const isEmergency = form.visit_type === "emergency" || form.priority === "emergency";

  return (
    <Card className="w-full p-6">
      <div className="flex items-center gap-2 mb-4 pb-2 border-b border-line text-ink/80">
        <Stethoscope size={18} className="text-teal-600" />
        <h3 className="font-display font-semibold text-base">Visit & Triage Details</h3>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
        <Field label="Visit Type" className="w-full">
          <Select
            className="w-full"
            value={form.visit_type}
            onChange={(e) => update("visit_type", e.target.value)}
          >
            <option value="outpatient">Outpatient</option>
            <option value="emergency">Emergency</option>
          </Select>
        </Field>

        <Field label="Priority Level" className="w-full">
          <Select
            className="w-full"
            value={form.priority}
            onChange={(e) => update("priority", e.target.value)}
          >
            <option value="routine">Routine</option>
            <option value="urgent">Urgent</option>
            <option value="emergency">Emergency</option>
          </Select>
        </Field>

        <Field label="Chief Complaint" className="col-span-1 md:col-span-2 w-full">
          <Textarea
            className="w-full"
            value={form.chief_complaint}
            onChange={(e) => update("chief_complaint", e.target.value)}
            placeholder="Describe current symptoms or primary reason for visit..."
            rows={3}
          />
        </Field>
      </div>

      {isEmergency && (
        <div className="mt-4 text-xs font-semibold text-red-600 bg-red-50 dark:bg-red-950/20 border border-red-200 rounded-xl p-3 flex items-center gap-2 animate-pulse w-full">
          <Siren size={16} className="shrink-0" />
          <span>Priority Alert: This encounter will trigger high-priority queue routing across departments.</span>
        </div>
      )}
    </Card>
  );
}

// ==================== RETURNING PATIENT ====================

function ReturningPatientPanel() {
  const [patientId, setPatientId] = useState(null);
  const [patientLabel, setPatientLabel] = useState("");
  const [resetKey, setResetKey] = useState(0);

  const [visit, setVisit] = useState({
    chief_complaint: "",
    visit_type: "outpatient",
    priority: "routine",
  });

  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState("");

  async function activate(e) {
    e.preventDefault();
    setError("");

    if (!patientId) {
      setError("Please search and select a returning patient first.");
      return;
    }

    setSubmitting(true);

    try {
      const { data: encounter, offline } = await createWithOfflineFallback(
        "encounter",
        "/encounters",
        {
          patient_id: patientId,
          ...visit,
        }
      );

      setResult({
        encounter,
        offline,
        patientLabel,
      });

      setPatientId(null);
      setPatientLabel("");
      setVisit({
        chief_complaint: "",
        visit_type: "outpatient",
        priority: "routine",
      });
      setResetKey((k) => k + 1);
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Could not open a new visit for this patient."
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (result) {
    return (
      <Card className="w-full border-moss/40 bg-emerald-50/50 p-6 rounded-2xl space-y-4">
        <div className="flex items-center gap-3 text-emerald-600">
          <CheckCircle2 size={24} />
          <h3 className="text-lg font-semibold">
            Patient Visit Opened {result.offline ? "(Saved Offline)" : ""}
          </h3>
        </div>

        <div className="bg-surface p-4 rounded-xl border border-line text-sm space-y-1 w-full">
          <p className="font-semibold text-ink">{result.patientLabel}</p>
          <p className="text-xs text-ink/60">
            New Visit MRN: <span className="font-mono font-medium text-teal-600">{result.offline ? "Pending Sync" : result.encounter.mrn}</span>
          </p>
        </div>

        <Button size="sm" variant="secondary" onClick={() => setResult(null)}>
          Reactivate Another Patient
        </Button>
      </Card>
    );
  }

  return (
    <form onSubmit={activate} className="w-full min-w-full max-w-full space-y-6">
      <Card className="w-full p-6">
        <div className="flex items-center gap-2 mb-2 pb-2 border-b border-line text-ink/80">
          <Search size={18} className="text-teal-600" />
          <h3 className="font-display font-semibold text-base">Find Existing Patient</h3>
        </div>

        <p className="text-xs text-ink/50 mb-4">
          Search by name, phone, or permanent Patient ID. Demographics and history carry over automatically.
        </p>

        <div className="w-full">
          <PatientLookup
            key={resetKey}
            label="Select Patient"
            onSelect={({ patientId, patientLabel }) => {
              setPatientId(patientId);
              setPatientLabel(patientLabel || "");
            }}
          />
        </div>
      </Card>

      <VisitDetailsCard
        form={visit}
        update={(field, value) => setVisit((v) => ({ ...v, [field]: value }))}
      />

      {error && (
        <div className="p-3 rounded-lg bg-red-50 text-red-600 border border-red-200 text-sm flex items-center gap-2 w-full">
          <AlertTriangle size={16} />
          {error}
        </div>
      )}

      <div className="flex justify-end w-full">
        <Button type="submit" size="lg" disabled={submitting} className="min-w-[220px]">
          {submitting ? "Activating..." : "Activate Visit"}
        </Button>
      </div>
    </form>
  );
}

// ==================== QUEUE MANAGEMENT ====================

function waitTime(createdAt) {
  const mins = Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000);
  if (mins < 60) return `${mins}m`;
  return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}

function QueuePanel() {
  const [encounters, setEncounters] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionError, setActionError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [reception, triage, emergency] = await Promise.all([
        api.get("/encounters", { params: { department: "reception" } }),
        api.get("/encounters", { params: { department: "triage" } }),
        api.get("/encounters", { params: { department: "emergency" } }),
      ]);

      const merged = [...reception.data, ...triage.data, ...emergency.data];
      merged.sort((a, b) => {
        if (a.is_emergency !== b.is_emergency) return a.is_emergency ? -1 : 1;
        return new Date(a.created_at) - new Date(b.created_at);
      });

      setEncounters(merged);
    } catch {
      setEncounters([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    const interval = setInterval(load, 30000);
    return () => clearInterval(interval);
  }, [load]);

  async function updatePriority(encounterId, priority) {
    setActionError("");
    try {
      await api.post(`/encounters/${encounterId}/transition`, { priority });
      load();
    } catch (err) {
      setActionError(err.response?.data?.message || "Could not update priority.");
    }
  }

  async function cancelEntry(encounterId) {
    if (!window.confirm("Cancel registration?")) return;
    setActionError("");
    try {
      await api.post(`/encounters/${encounterId}/close`, {
        outcome: "cancelled",
        disposition_notes: "Cancelled from reception queue",
      });
      load();
    } catch (err) {
      setActionError(err.response?.data?.message || "Could not cancel entry.");
    }
  }

  return (
    <Card className="w-full p-6">
      <div className="flex items-center justify-between border-b border-line pb-4 mb-4">
        <div className="flex items-center gap-2">
          <ListOrdered size={20} className="text-teal-600" />
          <h3 className="font-display font-semibold text-lg">Real-Time Intake Queue</h3>
        </div>

        <Badge tone="muted" className="px-3 py-1 text-xs">
          {encounters.length} Active Patients
        </Badge>
      </div>

      {actionError && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 text-red-600 border border-red-200 text-xs flex items-center gap-2 w-full">
          <AlertTriangle size={14} />
          {actionError}
        </div>
      )}

      {loading ? (
        <LoadingRow />
      ) : encounters.length === 0 ? (
        <EmptyState
          icon={ListOrdered}
          title="Queue Empty"
          hint="Newly admitted patients will populate here automatically."
        />
      ) : (
        <div className="space-y-2 w-full">
          {encounters.map((e) => (
            <div
              key={e.id}
              className={`p-3.5 rounded-xl border transition-all flex items-center justify-between flex-wrap gap-3 w-full ${
                e.is_emergency
                  ? "bg-red-50/60 dark:bg-red-950/20 border-red-200/80"
                  : "bg-surface border-line hover:border-teal-500/30"
              }`}
            >
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  {e.is_emergency && (
                    <Siren size={16} className="text-red-600 shrink-0 animate-bounce" />
                  )}
                  <span className="font-semibold text-sm text-ink">{e.patient?.full_name}</span>
                  <span className="text-xs font-mono text-ink/40">({e.mrn})</span>
                </div>

                <p className="text-xs text-ink/60 mt-0.5 line-clamp-1">
                  {e.chief_complaint || "No complaint noted"}
                </p>
              </div>

              <div className="flex items-center gap-3 shrink-0">
                <div className="flex items-center gap-1 text-xs text-ink/50 bg-surface-muted px-2.5 py-1 rounded-md border border-line">
                  <Clock size={12} />
                  <span>{waitTime(e.created_at)}</span>
                </div>

                <Badge tone="muted" className="capitalize text-xs">
                  {e.current_department}
                </Badge>

                {e.is_emergency ? (
                  <Badge tone="critical" icon={Siren} className="uppercase text-[10px] tracking-wide">
                    Emergency
                  </Badge>
                ) : (
                  <Select
                    value={e.priority}
                    onChange={(ev) => updatePriority(e.id, ev.target.value)}
                    className="text-xs py-1 px-2 h-8 w-auto min-w-[100px]"
                  >
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                  </Select>
                )}

                <Button
                  size="sm"
                  variant="ghost"
                  icon={X}
                  onClick={() => cancelEntry(e.id)}
                  aria-label="Cancel registration"
                  className="text-ink/40 hover:text-red-600"
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}