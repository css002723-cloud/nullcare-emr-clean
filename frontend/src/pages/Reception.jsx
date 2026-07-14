import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { ClipboardList } from "lucide-react";
import api from "../services/api";
import { Card, Field, Input, Select, Button, Badge, Textarea } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { createWithOfflineFallback } from "../offline/offlineResource";

const DISTRICTS = ["Blantyre", "Lilongwe", "Mzuzu", "Zomba", "Kasungu", "Mangochi", "Machinga", "Other"];
const REGIONS = ["Southern", "Central", "Northern"];

const initialForm = {
  given_name: "", family_name: "", sex: "", date_of_birth: "", estimated_age: "",
  national_id: "", phone: "", village: "", traditional_authority: "", district: "",
  region: "", occupation: "", guardian_name: "", guardian_relationship: "", guardian_phone: "",
  patient_category: "outpatient", chief_complaint: "", visit_type: "outpatient", priority: "routine",
};

export default function Reception() {
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
        given_name: form.given_name, family_name: form.family_name, national_id: form.national_id,
      });
      setDuplicates(res.data);
    } catch {
      // offline — duplicate check simply isn't available, proceed without it
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
        given_name: form.given_name, family_name: form.family_name, sex: form.sex,
        date_of_birth: form.date_of_birth || undefined,
        estimated_age: form.estimated_age ? Number(form.estimated_age) : undefined,
        national_id: form.national_id, phone: form.phone, village: form.village,
        traditional_authority: form.traditional_authority, district: form.district,
        region: form.region, occupation: form.occupation, guardian_name: form.guardian_name,
        guardian_relationship: form.guardian_relationship, guardian_phone: form.guardian_phone,
        patient_category: form.patient_category,
      };

      const { data: patient, offline: patientOffline } = await createWithOfflineFallback(
        "patient", "/patients", patientPayload
      );

      const encounterPayload = {
        patient_id: patient.id, visit_type: form.visit_type,
        chief_complaint: form.chief_complaint, priority: form.priority,
      };
      // If patient was queued offline, the encounter must reference the client_uuid
      // rather than a real numeric id (which doesn't exist yet) — store both.
      if (patientOffline) {
        encounterPayload.patient_client_uuid = patient.client_uuid;
      }

      const { data: encounter, offline: encounterOffline } = await createWithOfflineFallback(
        "encounter", "/encounters", encounterPayload
      );

      setResult({ patient, encounter, offline: patientOffline || encounterOffline });
      setForm(initialForm);
      setDuplicates([]);
    } catch (err) {
      setError(err.response?.data?.message || "Something went wrong while registering this patient.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="space-y-5 max-w-3xl">
      <PageHeader
        icon={ClipboardList}
        title="Reception — new arrival"
        subtitle="Register the patient, then open their visit. A hospital patient number (MRN) is generated automatically."
      />

      {result && (
        <Card className="border-moss/30 bg-moss/5">
          <p className="font-semibold text-moss">Patient registered{result.offline ? " (saved offline)" : ""}</p>
          <p className="text-sm mt-1">
            {result.patient.given_name} {result.patient.family_name} —{" "}
            <span className="mrn-mono">{result.offline ? "MRN pending sync" : result.patient.mrn}</span>
          </p>
          <p className="text-sm">Encounter opened: {result.offline ? "pending sync" : result.encounter.encounter_number}</p>
          {result.offline && (
            <p className="text-xs text-clay mt-2">
              This record is queued and will get its official MRN and encounter number once your connection is
              restored and the sync completes.
            </p>
          )}
          <div className="flex gap-2 mt-3">
            {!result.offline && (
              <Button size="sm" onClick={() => navigate(`/patients/${result.patient.id}`)}>
                Open patient record
              </Button>
            )}
            <Button size="sm" variant="secondary" onClick={() => setResult(null)}>
              Register another patient
            </Button>
          </div>
        </Card>
      )}

      {!result && (
        <form onSubmit={handleSubmit} className="space-y-5">
          <Card>
            <p className="font-display text-lg mb-4">Patient identity</p>
            <div className="grid md:grid-cols-2 gap-4">
              <Field label="Given name" required>
                <Input value={form.given_name} onChange={(e) => update("given_name", e.target.value)} onBlur={checkDuplicates} required />
              </Field>
              <Field label="Family name" required>
                <Input value={form.family_name} onChange={(e) => update("family_name", e.target.value)} onBlur={checkDuplicates} required />
              </Field>
              <Field label="Sex">
                <Select value={form.sex} onChange={(e) => update("sex", e.target.value)}>
                  <option value="">Select…</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </Select>
              </Field>
              <Field label="Date of birth" hint="Leave blank and use estimated age if unknown">
                <Input type="date" value={form.date_of_birth} onChange={(e) => update("date_of_birth", e.target.value)} />
              </Field>
              <Field label="Estimated age (years)">
                <Input type="number" min="0" max="120" value={form.estimated_age} onChange={(e) => update("estimated_age", e.target.value)} />
              </Field>
              <Field label="National ID">
                <Input value={form.national_id} onChange={(e) => update("national_id", e.target.value)} onBlur={checkDuplicates} />
              </Field>
              <Field label="Phone number">
                <Input value={form.phone} onChange={(e) => update("phone", e.target.value)} />
              </Field>
              <Field label="Patient category">
                <Select value={form.patient_category} onChange={(e) => update("patient_category", e.target.value)}>
                  {["outpatient", "inpatient", "student", "staff", "private", "referred", "emergency", "research"].map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </Select>
              </Field>
            </div>

            {checking && <p className="text-xs text-ink/40 mt-3">Checking for existing records…</p>}
            {duplicates.length > 0 && (
              <div className="mt-4 bg-clay/10 border border-clay/25 rounded-lg p-3">
                <p className="text-sm font-semibold text-clay">Possible existing record found</p>
                <ul className="text-sm mt-1 space-y-1">
                  {duplicates.map((d) => (
                    <li key={d.id}>
                      {d.full_name} — <span className="mrn-mono">{d.mrn}</span>{" "}
                      <button
                        type="button"
                        className="text-teal-600 underline"
                        onClick={() => navigate(`/patients/${d.id}`)}
                      >
                        open record
                      </button>
                    </li>
                  ))}
                </ul>
                <p className="text-xs text-ink/50 mt-1">
                  If this is the same person, open their existing record instead of creating a duplicate.
                </p>
              </div>
            )}
          </Card>

          <Card>
            <p className="font-display text-lg mb-4">Location & guardian</p>
            <div className="grid md:grid-cols-2 gap-4">
              <Field label="Village">
                <Input value={form.village} onChange={(e) => update("village", e.target.value)} />
              </Field>
              <Field label="Traditional Authority">
                <Input value={form.traditional_authority} onChange={(e) => update("traditional_authority", e.target.value)} />
              </Field>
              <Field label="District">
                <Select value={form.district} onChange={(e) => update("district", e.target.value)}>
                  <option value="">Select…</option>
                  {DISTRICTS.map((d) => <option key={d} value={d}>{d}</option>)}
                </Select>
              </Field>
              <Field label="Region">
                <Select value={form.region} onChange={(e) => update("region", e.target.value)}>
                  <option value="">Select…</option>
                  {REGIONS.map((r) => <option key={r} value={r}>{r}</option>)}
                </Select>
              </Field>
              <Field label="Occupation / school / workplace">
                <Input value={form.occupation} onChange={(e) => update("occupation", e.target.value)} />
              </Field>
              <Field label="Guardian / next of kin name">
                <Input value={form.guardian_name} onChange={(e) => update("guardian_name", e.target.value)} />
              </Field>
              <Field label="Relationship">
                <Input value={form.guardian_relationship} onChange={(e) => update("guardian_relationship", e.target.value)} />
              </Field>
              <Field label="Guardian phone">
                <Input value={form.guardian_phone} onChange={(e) => update("guardian_phone", e.target.value)} />
              </Field>
            </div>
          </Card>

          <Card>
            <p className="font-display text-lg mb-4">This visit</p>
            <div className="grid md:grid-cols-2 gap-4">
              <Field label="Visit type">
                <Select value={form.visit_type} onChange={(e) => update("visit_type", e.target.value)}>
                  <option value="outpatient">Outpatient</option>
                  <option value="emergency">Emergency</option>
                </Select>
              </Field>
              <Field label="Priority">
                <Select value={form.priority} onChange={(e) => update("priority", e.target.value)}>
                  <option value="routine">Routine</option>
                  <option value="urgent">Urgent</option>
                  <option value="emergency">Emergency</option>
                </Select>
              </Field>
              <Field label="Chief complaint" className="md:col-span-2">
                <Textarea value={form.chief_complaint} onChange={(e) => update("chief_complaint", e.target.value)} placeholder="Reason for today's visit" />
              </Field>
            </div>
          </Card>

          {error && <p role="alert" className="text-sm text-alert">{error}</p>}

          <Button type="submit" size="lg" disabled={submitting}>
            {submitting ? "Registering…" : "Register patient & open visit"}
          </Button>
        </form>
      )}
    </div>
  );
}
