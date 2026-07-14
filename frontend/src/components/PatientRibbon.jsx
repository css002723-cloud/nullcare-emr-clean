import { AlertTriangle, ShieldCheck, FileText } from "lucide-react";
import { Badge, calcAge } from "./ui";

/**
 * The one element every clinical screen shares: once a patient is open, this bar stays
 * pinned to the top of the workspace so a clinician can never lose track of *who* they're
 * looking at, mid-interruption, mid-shift-change, mid-emergency. Allergies and any active
 * critical flag are always visible here, not buried a tab away.
 */
export default function PatientRibbon({ patient, encounter, extra }) {
  if (!patient) return null;
  const age = calcAge(patient.date_of_birth, patient.estimated_age);
  const allergies = patient.allergies || [];

  return (
    <div className="sticky top-14 md:top-0 z-20 -mx-4 sm:-mx-6 md:-mx-8 px-4 sm:px-6 md:px-8 py-3 bg-teal-500 text-white shadow-md">
      <div className="max-w-6xl mx-auto flex flex-wrap items-center gap-x-5 gap-y-2 overflow-x-auto">
        <div>
          <p className="font-display text-lg leading-tight">{patient.full_name}</p>
          <p className="text-xs text-teal-100 mrn-mono">
            {patient.mrn} · {patient.sex || "sex unknown"} · {age !== null ? `${age}y` : "age unknown"}
          </p>
        </div>

        {encounter && (
          <div className="text-xs text-teal-100 border-l border-teal-300/40 pl-5">
            <p className="uppercase tracking-wide text-teal-200 text-[10px] font-semibold flex items-center gap-1">
              <FileText size={11} /> Encounter
            </p>
            <p className="mrn-mono">{encounter.encounter_number}</p>
          </div>
        )}

        {allergies.length > 0 ? (
          <div className="border-l border-teal-300/40 pl-5 flex items-center gap-1.5">
            <span className="text-[10px] uppercase tracking-wide text-teal-200 font-semibold flex items-center gap-1">
              <AlertTriangle size={11} /> Allergies
            </span>
            {allergies.map((a) => (
              <Badge key={a.id} tone={a.severity === "severe" ? "critical" : "warning"}>
                {a.substance}
              </Badge>
            ))}
          </div>
        ) : (
          <div className="border-l border-teal-300/40 pl-5">
            <Badge tone="muted" icon={ShieldCheck}>No known allergies</Badge>
          </div>
        )}

        {patient.is_deceased && (
          <Badge tone="critical" icon={AlertTriangle}>Deceased</Badge>
        )}

        {extra && <div className="ml-auto flex items-center gap-2">{extra}</div>}
      </div>
    </div>
  );
}
