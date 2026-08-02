
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
  "Machinga",
  "Other",
];

const REGIONS = ["Southern", "Central", "Northern"];

const TABS = [
  { value: "new", label: "New patient", icon: UserPlus },
  { value: "returning", label: "Returning patient", icon: UserCheck },
  { value: "queue", label: "Queue", icon: ListOrdered },
];

export default function Reception() {
  const [tab, setTab] = useState("new");

  return (
    <div className="space-y-5 max-w-3xl">
      <PageHeader
        icon={ClipboardList}
        title="Reception"
        subtitle="Register new arrivals, bring back returning patients, and manage today's waiting queue."
      />

      <div className="flex gap-2 flex-wrap">
        {TABS.map((t) => (
          <button
            key={t.value}
            onClick={() => setTab(t.value)}
            className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors ${
              tab === t.value
                ? "bg-teal-500 text-white"
                : "bg-surface border border-line text-ink/60"
            }`}
          >
            <t.icon size={14} /> {t.label}
          </button>
        ))}
      </div>

      {tab === "new" && <NewPatientPanel />}
      {tab === "returning" && <ReturningPatientPanel />}
      {tab === "queue" && <QueuePanel />}
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

  // Research consent
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

        estimated_age: form.estimated_age
          ? Number(form.estimated_age)
          : undefined,

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

        // IMPORTANT:
        // Send research consent to the backend.
        consent_research: form.consent_research,
      };

      const {
        data: patient,
        offline: patientOffline,
      } = await createWithOfflineFallback(
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

      const {
        data: encounter,
        offline: encounterOffline,
      } = await createWithOfflineFallback(
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
      <Card className="border-moss/30 bg-moss/5">
        <p className="font-semibold text-moss">
          Patient registered
          {result.offline ? " (saved offline)" : ""}
        </p>

        <p className="text-sm mt-1">
          {result.patient.given_name} {result.patient.family_name} — Patient
          ID:{" "}
          <span className="mrn-mono">
            {result.offline
              ? "pending sync"
              : result.patient.patient_uid}
          </span>
        </p>

        <p className="text-sm">
          This visit's MRN:{" "}
          <span className="mrn-mono">
            {result.offline
              ? "pending sync"
              : result.encounter.mrn}
          </span>
        </p>

        <p className="text-xs text-ink/50 mt-2">
          Research consent:{" "}
          <span className="font-medium">
            {result.patient.consent_research
              ? "Given"
              : "Not given"}
          </span>
        </p>

        {result.offline && (
          <p className="text-xs text-clay mt-2">
            This record is queued and will get its official patient ID and
            visit MRN once your connection is restored and the sync completes.
          </p>
        )}

        <div className="flex gap-2 mt-3">
          {!result.offline && (
            <Button
              size="sm"
              onClick={() =>
                navigate(
                  `/patients/${result.patient.patient_uid}`
                )
              }
            >
              Open patient record
            </Button>
          )}

          <Button
            size="sm"
            variant="secondary"
            onClick={() => setResult(null)}
          >
            Register another patient
          </Button>
        </div>
      </Card>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      {/* ==================== PATIENT IDENTITY ==================== */}

      <Card>
        <p className="font-display text-lg mb-4">
          Patient identity
        </p>

        <div className="grid md:grid-cols-2 gap-4">
          <Field label="Given name" required>
            <Input
              value={form.given_name}
              onChange={(e) =>
                update("given_name", e.target.value)
              }
              onBlur={checkDuplicates}
              required
            />
          </Field>

          <Field label="Family name" required>
            <Input
              value={form.family_name}
              onChange={(e) =>
                update("family_name", e.target.value)
              }
              onBlur={checkDuplicates}
              required
            />
          </Field>

          <Field label="Sex">
            <Select
              value={form.sex}
              onChange={(e) =>
                update("sex", e.target.value)
              }
            >
              <option value="">Select…</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </Select>
          </Field>

          <Field
            label="Date of birth"
            hint="Leave blank and use estimated age if unknown"
          >
            <Input
              type="date"
              value={form.date_of_birth}
              onChange={(e) =>
                update("date_of_birth", e.target.value)
              }
            />
          </Field>

          <Field label="Estimated age (years)">
            <Input
              type="number"
              min="0"
              max="120"
              value={form.estimated_age}
              onChange={(e) =>
                update("estimated_age", e.target.value)
              }
            />
          </Field>

          <Field label="National ID">
            <Input
              value={form.national_id}
              onChange={(e) =>
                update("national_id", e.target.value)
              }
              onBlur={checkDuplicates}
            />
          </Field>

          <Field label="Phone number">
            <Input
              value={form.phone}
              onChange={(e) =>
                update("phone", e.target.value)
              }
            />
          </Field>

          <Field label="Patient category">
            <Select
              value={form.patient_category}
              onChange={(e) =>
                update(
                  "patient_category",
                  e.target.value
                )
              }
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
                  {c}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        {checking && (
          <p className="text-xs text-ink/40 mt-3">
            Checking for existing records…
          </p>
        )}

        {duplicates.length > 0 && (
          <div className="mt-4 bg-clay/10 border border-clay/25 rounded-lg p-3">
            <p className="text-sm font-semibold text-clay">
              Possible existing record found
            </p>

            <ul className="text-sm mt-1 space-y-1">
              {duplicates.map((d) => (
                <li key={d.id}>
                  {d.full_name} —{" "}
                  <span className="mrn-mono">
                    {d.patient_uid}
                  </span>{" "}
                  <button
                    type="button"
                    className="text-teal-600 underline"
                    onClick={() =>
                      navigate(
                        `/patients/${d.patient_uid}`
                      )
                    }
                  >
                    open record
                  </button>
                </li>
              ))}
            </ul>

            <p className="text-xs text-ink/50 mt-1">
              If this is the same person, use the "Returning
              patient" tab instead of creating a duplicate.
            </p>
          </div>
        )}
      </Card>

      {/* ==================== LOCATION & GUARDIAN ==================== */}

      <Card>
        <p className="font-display text-lg mb-4">
          Location & guardian
        </p>

        <div className="grid md:grid-cols-2 gap-4">
          <Field label="Village">
            <Input
              value={form.village}
              onChange={(e) =>
                update("village", e.target.value)
              }
            />
          </Field>

          <Field label="Traditional Authority">
            <Input
              value={form.traditional_authority}
              onChange={(e) =>
                update(
                  "traditional_authority",
                  e.target.value
                )
              }
            />
          </Field>

          <Field label="District">
            <Select
              value={form.district}
              onChange={(e) =>
                update("district", e.target.value)
              }
            >
              <option value="">Select…</option>

              {DISTRICTS.map((d) => (
                <option key={d} value={d}>
                  {d}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Region">
            <Select
              value={form.region}
              onChange={(e) =>
                update("region", e.target.value)
              }
            >
              <option value="">Select…</option>

              {REGIONS.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Occupation / school / workplace">
            <Input
              value={form.occupation}
              onChange={(e) =>
                update("occupation", e.target.value)
              }
            />
          </Field>

          <Field label="Guardian / next of kin name">
            <Input
              value={form.guardian_name}
              onChange={(e) =>
                update("guardian_name", e.target.value)
              }
            />
          </Field>

          <Field label="Relationship">
            <Input
              value={form.guardian_relationship}
              onChange={(e) =>
                update(
                  "guardian_relationship",
                  e.target.value
                )
              }
            />
          </Field>

          <Field label="Guardian phone">
            <Input
              value={form.guardian_phone}
              onChange={(e) =>
                update(
                  "guardian_phone",
                  e.target.value
                )
              }
            />
          </Field>
        </div>
      </Card>

      {/* ==================== RESEARCH CONSENT ==================== */}

      <ResearchConsentCard
        value={form.consent_research}
        onChange={(value) =>
          update("consent_research", value)
        }
      />

      {/* ==================== VISIT DETAILS ==================== */}

      <VisitDetailsCard
        form={form}
        update={update}
      />

      {error && (
        <p
          role="alert"
          className="text-sm text-alert"
        >
          {error}
        </p>
      )}

      <Button
        type="submit"
        size="lg"
        disabled={submitting}
      >
        {submitting
          ? "Registering…"
          : "Register patient & open visit"}
      </Button>
    </form>
  );
}

// ==================== RESEARCH CONSENT ====================

function ResearchConsentCard({ value, onChange }) {
  return (
    <Card>
      <div className="flex items-start gap-3">
        <div className="pt-0.5">
          <input
            id="consent-research"
            type="checkbox"
            checked={value}
            onChange={(e) =>
              onChange(e.target.checked)
            }
            className="h-4 w-4 rounded border-line text-teal-600 focus:ring-teal-500"
          />
        </div>

        <div className="min-w-0">
          <label
            htmlFor="consent-research"
            className="font-medium text-sm cursor-pointer"
          >
            Consent to research use
          </label>

          <p className="text-xs text-ink/55 mt-1 leading-relaxed">
            The patient has agreed that their health
            information may be included in approved
            clinical or research activities. Only
            appropriately authorized and de-identified
            information should be used for research.
          </p>

          <p className="text-xs text-ink/40 mt-2">
            Leave unchecked if the patient has not given
            consent.
          </p>

          <div
            className={`mt-3 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
              value
                ? "bg-teal-500/10 text-teal-700"
                : "bg-ink/5 text-ink/50"
            }`}
          >
            {value
              ? "Consent given"
              : "Consent not given"}
          </div>
        </div>
      </div>
    </Card>
  );
}

// ==================== VISIT DETAILS ====================

function VisitDetailsCard({ form, update }) {
  return (
    <Card>
      <p className="font-display text-lg mb-4">
        This visit
      </p>

      <div className="grid md:grid-cols-2 gap-4">
        <Field label="Visit type">
          <Select
            value={form.visit_type}
            onChange={(e) =>
              update("visit_type", e.target.value)
            }
          >
            <option value="outpatient">
              Outpatient
            </option>

            <option value="emergency">
              Emergency
            </option>
          </Select>
        </Field>

        <Field label="Priority">
          <Select
            value={form.priority}
            onChange={(e) =>
              update("priority", e.target.value)
            }
          >
            <option value="routine">Routine</option>
            <option value="urgent">Urgent</option>
            <option value="emergency">
              Emergency
            </option>
          </Select>
        </Field>

        <Field
          label="Chief complaint"
          className="md:col-span-2"
        >
          <Textarea
            value={form.chief_complaint}
            onChange={(e) =>
              update(
                "chief_complaint",
                e.target.value
              )
            }
            placeholder="Reason for today's visit"
          />
        </Field>
      </div>

      {(form.visit_type === "emergency" ||
        form.priority === "emergency") && (
        <p className="mt-3 text-xs text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2 flex items-center gap-1.5">
          <Siren size={13} />
          This visit will be flagged as an emergency and
          prioritized at the top of every queue.
        </p>
      )}
    </Card>
  );
}

// ==================== RETURNING PATIENT ====================

function ReturningPatientPanel() {
  const [patientId, setPatientId] = useState(null);
  const [patientLabel, setPatientLabel] =
    useState("");
  const [resetKey, setResetKey] = useState(0);

  const [visit, setVisit] = useState({
    chief_complaint: "",
    visit_type: "outpatient",
    priority: "routine",
  });

  const [submitting, setSubmitting] =
    useState(false);

  const [result, setResult] =
    useState(null);

  const [error, setError] =
    useState("");

  async function activate(e) {
    e.preventDefault();
    setError("");

    if (!patientId) {
      setError(
        "Search for and select the returning patient first."
      );
      return;
    }

    setSubmitting(true);

    try {
      const {
        data: encounter,
        offline,
      } = await createWithOfflineFallback(
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
          "Couldn't open a new visit for this patient — please try again."
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (result) {
    return (
      <Card className="border-moss/30 bg-moss/5">
        <p className="font-semibold text-moss">
          Patient activated for today's visit
          {result.offline
            ? " (saved offline)"
            : ""}
        </p>

        <p className="text-sm mt-1">
          {result.patientLabel}
        </p>

        <p className="text-sm">
          New visit MRN:{" "}
          <span className="mrn-mono">
            {result.offline
              ? "pending sync"
              : result.encounter.mrn}
          </span>{" "}
          — their permanent patient ID has not changed.
        </p>

        <p className="text-xs text-ink/50 mt-1">
          The patient now moves to triage and nursing,
          exactly like a new arrival.
        </p>

        <Button
          size="sm"
          variant="secondary"
          className="mt-3"
          onClick={() => setResult(null)}
        >
          Activate another returning patient
        </Button>
      </Card>
    );
  }

  return (
    <form
      onSubmit={activate}
      className="space-y-5"
    >
      <Card>
        <p className="font-display text-lg mb-1">
          Find the returning patient
        </p>

        <p className="text-xs text-ink/50 mb-3">
          No new registration needed — search by name
          or their permanent patient ID. Their record,
          allergies, and visit history all carry over;
          only a fresh visit MRN is generated for today.
        </p>

        <PatientLookup
          key={resetKey}
          label="Patient"
          onSelect={({
            patientId,
            patientLabel,
          }) => {
            setPatientId(patientId);
            setPatientLabel(
              patientLabel || ""
            );
          }}
        />
      </Card>

      <VisitDetailsCard
        form={visit}
        update={(field, value) =>
          setVisit((v) => ({
            ...v,
            [field]: value,
          }))
        }
      />

      {error && (
        <p
          role="alert"
          className="text-sm text-alert"
        >
          {error}
        </p>
      )}

      <Button
        type="submit"
        size="lg"
        disabled={submitting}
      >
        {submitting
          ? "Activating…"
          : "Activate patient & open visit"}
      </Button>
    </form>
  );
}

// ==================== QUEUE MANAGEMENT ====================

function waitTime(createdAt) {
  const mins = Math.floor(
    (Date.now() -
      new Date(createdAt).getTime()) /
      60000
  );

  if (mins < 60) {
    return `${mins}m`;
  }

  return `${Math.floor(mins / 60)}h ${
    mins % 60
  }m`;
}

function QueuePanel() {
  const [encounters, setEncounters] =
    useState([]);

  const [loading, setLoading] =
    useState(true);

  const load = useCallback(async () => {
    setLoading(true);

    try {
      const [
        reception,
        triage,
        emergency,
      ] = await Promise.all([
        api.get("/encounters", {
          params: {
            department: "reception",
          },
        }),

        api.get("/encounters", {
          params: {
            department: "triage",
          },
        }),

        api.get("/encounters", {
          params: {
            department: "emergency",
          },
        }),
      ]);

      const merged = [
        ...reception.data,
        ...triage.data,
        ...emergency.data,
      ];

      merged.sort((a, b) => {
        if (
          a.is_emergency !==
          b.is_emergency
        ) {
          return a.is_emergency
            ? -1
            : 1;
        }

        return (
          new Date(a.created_at) -
          new Date(b.created_at)
        );
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

    const interval = setInterval(
      load,
      30000
    );

    return () =>
      clearInterval(interval);
  }, [load]);

  async function updatePriority(
    encounterId,
    priority
  ) {
    await api.post(
      `/encounters/${encounterId}/transition`,
      { priority }
    );

    load();
  }

  async function cancelEntry(
    encounterId
  ) {
    if (
      !window.confirm(
        "Cancel this registration? Use this only for a mistaken entry or a no-show."
      )
    ) {
      return;
    }

    await api.post(
      `/encounters/${encounterId}/close`,
      {
        outcome: "cancelled",
        disposition_notes:
          "Cancelled from reception queue",
      }
    );

    load();
  }

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <p className="font-display text-lg">
          Today's queue
        </p>

        <Badge tone="muted">
          {encounters.length} waiting
        </Badge>
      </div>

      {loading ? (
        <LoadingRow />
      ) : encounters.length === 0 ? (
        <EmptyState
          icon={ListOrdered}
          title="No one waiting right now"
          hint="New arrivals and returning patients will appear here."
        />
      ) : (
        <ul className="divide-y divide-line">
          {encounters.map((e) => (
            <li
              key={e.id}
              className={`py-3 flex items-center justify-between flex-wrap gap-2 ${
                e.is_emergency
                  ? "bg-alert/5 -mx-2 px-2 rounded"
                  : ""
              }`}
            >
              <div className="min-w-0">
                <p className="font-medium flex items-center gap-1.5">
                  {e.is_emergency && (
                    <Siren
                      size={14}
                      className="text-alert shrink-0"
                    />
                  )}

                  {e.patient?.full_name}

                  <span className="text-xs text-ink/40 font-normal mrn-mono">
                    ({e.mrn})
                  </span>
                </p>

                <p className="text-xs text-ink/50">
                  {e.chief_complaint ||
                    "No complaint noted"}
                </p>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <span className="text-xs text-ink/45 flex items-center gap-1">
                  <Clock size={12} />
                  {waitTime(e.created_at)}
                </span>

                <Badge tone="muted">
                  {e.current_department}
                </Badge>

                {e.is_emergency ? (
                  <Badge
                    tone="critical"
                    icon={Siren}
                  >
                    emergency
                  </Badge>
                ) : (
                  <Select
                    value={e.priority}
                    onChange={(ev) =>
                      updatePriority(
                        e.id,
                        ev.target.value
                      )
                    }
                    className="text-xs py-1 w-auto"
                  >
                    <option value="routine">
                      Routine
                    </option>

                    <option value="urgent">
                      Urgent
                    </option>

                    <option value="emergency">
                      Emergency
                    </option>
                  </Select>
                )}

                <Button
                  size="sm"
                  variant="ghost"
                  icon={X}
                  onClick={() =>
                    cancelEntry(e.id)
                  }
                  aria-label="Cancel this registration"
                >
                  Cancel
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
