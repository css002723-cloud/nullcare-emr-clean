import { useState, useEffect } from "react";
import { FileSignature, ChevronDown, ChevronUp, Stethoscope } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea } from "./ui";
import { createWithOfflineFallback } from "../offline/offlineResource";

const NOTE_TYPES = [
  { value: "history_physical", label: "History & Physical", structured: true },
  { value: "consult", label: "Consultation note", structured: true },
  { value: "progress", label: "Progress note", structured: false },
  { value: "nursing", label: "Nursing care plan note", structured: false },
  { value: "procedure", label: "Procedure note", structured: false },
  { value: "discharge_summary", label: "Discharge summary", structured: false },
];

const EMPTY_FORM = {
  note_type: "history_physical", clinic_template: "",
  presenting_complaint: "", history_of_presenting_illness: "",
  past_medical_history: "", past_surgical_history: "", medication_history: "",
  allergy_history: "", social_history: "", family_history: "",
  review_of_systems: "", examination_findings: "",
  diagnosis: "", icd_code: "", differential_diagnosis: "",
  plan: "", follow_up_plan: "", body: "",
};

/**
 * Full structured outpatient documentation: presenting complaint through follow-up plan,
 * with optional clinic-specific templates that prompt (not force) relevant review-of-systems
 * and examination items. Every saved note is stamped with an explicit signature — the
 * signing clinician's name and the exact time — displayed on every note, not just logged
 * invisibly to the audit trail.
 */
export default function ClinicalNoteForm({ encounterId, notes, canWrite, onSaved, allowedTypes, title = "Clinical documentation" }) {
  const [templates, setTemplates] = useState({});
  const noteTypeOptions = allowedTypes ? NOTE_TYPES.filter((t) => allowedTypes.includes(t.value)) : NOTE_TYPES;
  const [form, setForm] = useState({ ...EMPTY_FORM, note_type: noteTypeOptions[0]?.value || "progress" });
  const [showForm, setShowForm] = useState(false);
  const [expandedNoteId, setExpandedNoteId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    api.get("/encounters/note-templates").then((res) => setTemplates(res.data)).catch(() => {});
  }, []);

  const noteTypeMeta = NOTE_TYPES.find((t) => t.value === form.note_type);
  const template = form.clinic_template ? templates[form.clinic_template] : null;

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await createWithOfflineFallback("note", `/encounters/${encounterId}/notes`, form);
      setForm({ ...EMPTY_FORM, note_type: noteTypeOptions[0]?.value || "progress" });
      setShowForm(false);
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save this note — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <p className="font-display text-lg flex items-center gap-2">
          <Stethoscope size={18} strokeWidth={2} className="text-teal-500" /> {title}
        </p>
        {canWrite && (
          <Button size="sm" variant={showForm ? "ghost" : "primary"} onClick={() => setShowForm((s) => !s)}>
            {showForm ? "Cancel" : "+ New note"}
          </Button>
        )}
      </div>

      <div className="space-y-2 mb-4">
        {notes.length === 0 && <p className="text-sm text-ink/40">No notes recorded for this visit yet.</p>}
        {notes.map((n) => (
          <NoteCard key={n.id} note={n} expanded={expandedNoteId === n.id} onToggle={() => setExpandedNoteId(expandedNoteId === n.id ? null : n.id)} />
        ))}
      </div>

      {showForm && canWrite && (
        <form onSubmit={submit} className="space-y-4 border-t border-line pt-4">
          <div className="grid md:grid-cols-2 gap-3">
            <Field label="Note type">
              <Select value={form.note_type} onChange={(e) => update("note_type", e.target.value)}>
                {noteTypeOptions.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </Select>
            </Field>
            {noteTypeMeta?.structured && (
              <Field label="Clinic template" hint="Optional — prompts relevant review-of-systems and exam items for this clinic">
                <Select value={form.clinic_template} onChange={(e) => update("clinic_template", e.target.value)}>
                  <option value="">General / no template</option>
                  {Object.entries(templates).map(([key, t]) => <option key={key} value={key}>{t.label}</option>)}
                </Select>
              </Field>
            )}
          </div>

          {noteTypeMeta?.structured ? (
            <StructuredFields form={form} update={update} template={template} />
          ) : (
            <>
              <Field label="Diagnosis"><Input value={form.diagnosis} onChange={(e) => update("diagnosis", e.target.value)} /></Field>
              <Field label="Plan"><Textarea value={form.plan} onChange={(e) => update("plan", e.target.value)} /></Field>
              {form.note_type === "discharge_summary" && (
                <Field label="Follow-up plan"><Textarea value={form.follow_up_plan} onChange={(e) => update("follow_up_plan", e.target.value)} /></Field>
              )}
              <Field label="Notes"><Textarea value={form.body} onChange={(e) => update("body", e.target.value)} /></Field>
            </>
          )}

          {error && <p role="alert" className="text-sm text-alert">{error}</p>}

          <div className="bg-surface-alt rounded-lg px-3 py-2 text-xs text-ink/50 flex items-center gap-1.5">
            <FileSignature size={13} />
            Saving this note electronically signs it with your name and the current time.
          </div>

          <Button type="submit" disabled={saving}>{saving ? "Signing & saving…" : "Sign & save note"}</Button>
        </form>
      )}
    </Card>
  );
}

function StructuredFields({ form, update, template }) {
  const rosPlaceholder = template?.review_of_systems_prompts?.join("\n") || "Comment on relevant systems…";
  const examPlaceholder = template?.examination_prompts?.join("\n") || "General appearance, vital signs, relevant systems…";

  return (
    <div className="space-y-4">
      <div>
        <p className="text-xs font-semibold text-ink/50 uppercase mb-2">History</p>
        <div className="grid md:grid-cols-2 gap-3">
          <Field label="Presenting complaint"><Textarea value={form.presenting_complaint} onChange={(e) => update("presenting_complaint", e.target.value)} /></Field>
          <Field label="History of presenting illness"><Textarea value={form.history_of_presenting_illness} onChange={(e) => update("history_of_presenting_illness", e.target.value)} /></Field>
          <Field label="Past medical history"><Textarea value={form.past_medical_history} onChange={(e) => update("past_medical_history", e.target.value)} /></Field>
          <Field label="Past surgical history"><Textarea value={form.past_surgical_history} onChange={(e) => update("past_surgical_history", e.target.value)} /></Field>
          <Field label="Medication history"><Textarea value={form.medication_history} onChange={(e) => update("medication_history", e.target.value)} /></Field>
          <Field label="Allergy history" hint="Narrative context — the structured allergy list is on the patient's chart">
            <Textarea value={form.allergy_history} onChange={(e) => update("allergy_history", e.target.value)} />
          </Field>
          <Field label="Social history"><Textarea value={form.social_history} onChange={(e) => update("social_history", e.target.value)} /></Field>
          <Field label="Family history"><Textarea value={form.family_history} onChange={(e) => update("family_history", e.target.value)} /></Field>
        </div>
      </div>

      <div>
        <p className="text-xs font-semibold text-ink/50 uppercase mb-2">Examination</p>
        <div className="grid md:grid-cols-2 gap-3">
          <Field label="Review of systems">
            <Textarea value={form.review_of_systems} onChange={(e) => update("review_of_systems", e.target.value)} placeholder={rosPlaceholder} />
          </Field>
          <Field label="Examination findings">
            <Textarea value={form.examination_findings} onChange={(e) => update("examination_findings", e.target.value)} placeholder={examPlaceholder} />
          </Field>
        </div>
      </div>

      <div>
        <p className="text-xs font-semibold text-ink/50 uppercase mb-2">Assessment</p>
        <div className="grid md:grid-cols-3 gap-3">
          <Field label="Diagnosis" className="md:col-span-2"><Input value={form.diagnosis} onChange={(e) => update("diagnosis", e.target.value)} /></Field>
          <Field label="ICD code"><Input value={form.icd_code} onChange={(e) => update("icd_code", e.target.value)} placeholder="e.g. J06.9" /></Field>
          <Field label="Differential diagnosis" className="md:col-span-3"><Textarea value={form.differential_diagnosis} onChange={(e) => update("differential_diagnosis", e.target.value)} /></Field>
        </div>
      </div>

      <div>
        <p className="text-xs font-semibold text-ink/50 uppercase mb-2">Plan</p>
        <p className="text-xs text-ink/45 mb-2">
          Orders, prescriptions, and referrals are placed in their own panels below — they'll be linked to this visit automatically.
        </p>
        <div className="grid md:grid-cols-2 gap-3">
          <Field label="Clinical plan"><Textarea value={form.plan} onChange={(e) => update("plan", e.target.value)} /></Field>
          <Field label="Follow-up plan"><Textarea value={form.follow_up_plan} onChange={(e) => update("follow_up_plan", e.target.value)} /></Field>
        </div>
      </div>
    </div>
  );
}

function NoteCard({ note, expanded, onToggle }) {
  const label = NOTE_TYPES.find((t) => t.value === note.note_type)?.label || note.note_type?.replace("_", " ");
  const hasStructuredContent = note.history_of_presenting_illness || note.past_medical_history || note.review_of_systems;

  return (
    <div className="border border-line rounded-lg overflow-hidden">
      <button onClick={onToggle} className="w-full flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-surface-alt/60 transition-colors">
        <div className="flex items-center gap-2 min-w-0">
          {expanded ? <ChevronUp size={14} className="text-ink/40 shrink-0" /> : <ChevronDown size={14} className="text-ink/40 shrink-0" />}
          <span className="text-sm font-semibold capitalize truncate">{label}</span>
          {note.diagnosis && <span className="text-xs text-ink/50 truncate">— {note.diagnosis}</span>}
        </div>
        <span className="text-xs text-ink/40 shrink-0">{new Date(note.created_at).toLocaleDateString()}</span>
      </button>

      {expanded && (
        <div className="px-3 pb-3 space-y-2 text-sm border-t border-line pt-2">
          {hasStructuredContent ? (
            <>
              <NoteField label="Presenting complaint" value={note.presenting_complaint} />
              <NoteField label="History of presenting illness" value={note.history_of_presenting_illness} />
              <NoteField label="Past medical history" value={note.past_medical_history} />
              <NoteField label="Past surgical history" value={note.past_surgical_history} />
              <NoteField label="Medication history" value={note.medication_history} />
              <NoteField label="Allergy history" value={note.allergy_history} />
              <NoteField label="Social history" value={note.social_history} />
              <NoteField label="Family history" value={note.family_history} />
              <NoteField label="Review of systems" value={note.review_of_systems} />
              <NoteField label="Examination findings" value={note.examination_findings} />
              <NoteField label="Diagnosis" value={note.diagnosis} extra={note.icd_code} />
              <NoteField label="Differential diagnosis" value={note.differential_diagnosis} />
              <NoteField label="Plan" value={note.plan} />
              <NoteField label="Follow-up plan" value={note.follow_up_plan} />
            </>
          ) : (
            <>
              <NoteField label="Diagnosis" value={note.diagnosis} />
              <NoteField label="Plan" value={note.plan} />
              <NoteField label="Follow-up plan" value={note.follow_up_plan} />
              <NoteField label="Notes" value={note.body} />
            </>
          )}

          <div className="flex items-center gap-1.5 text-xs text-ink/45 pt-2 border-t border-line mt-2">
            <FileSignature size={12} />
            {note.signed && note.signed_by_name ? (
              <span>Electronically signed by <span className="font-medium text-ink/60">{note.signed_by_name}</span> ({note.author_role}) on {new Date(note.signed_at).toLocaleString()}</span>
            ) : (
              <span>Author: {note.author_role || "unknown"}</span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function NoteField({ label, value, extra }) {
  if (!value) return null;
  return (
    <p>
      <span className="font-semibold">{label}:</span> {value}
      {extra && <Badge tone="muted" className="ml-1.5">{extra}</Badge>}
    </p>
  );
}
