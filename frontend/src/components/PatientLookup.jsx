import { useState, useRef, useEffect } from "react";
import { Search, X, User } from "lucide-react";
import api from "../services/api";
import { Input, Select, Badge, calcAge } from "./ui";

const CLOSED_STAGES = ["discharged", "closed", "deceased"];

/**
 * Lets staff find a patient by typing a name or MRN instead of ever needing to know the
 * raw internal patient/encounter ID. Optionally also asks which of that patient's open
 * visits this action applies to (requireEncounter=true), since most clinical actions
 * (lab orders, imaging, billing) need to know which encounter, not just which person.
 *
 * Calls onSelect({ patientId, patientLabel, encounterId, encounterLabel }) once resolved.
 */
export default function PatientLookup({ requireEncounter = false, onSelect, onClear, label }) {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const [fetchError, setFetchError] = useState(false);
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [encounters, setEncounters] = useState([]);
  const [selectedEncounterId, setSelectedEncounterId] = useState("");
  const [loadingEncounters, setLoadingEncounters] = useState(false);
  const [encounterLookupError, setEncounterLookupError] = useState(false);
  const debounceRef = useRef(null);
  const containerRef = useRef(null);

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setShowResults(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  async function loadPatients(query = "") {
    setSearching(true);
    setFetchError(false);
    try {
      const params = { status: "all" };
      if (query.trim()) {
        params.q = query.trim();
      }
      const res = await api.get("/patients", { params });
      setResults(res.data || []);
      setShowResults(true);
    } catch (err) {
      console.error('Patient lookup failed', err);
      setResults([]);
      setFetchError(true);
      setShowResults(true);
    } finally {
      setSearching(false);
    }
  }

  useEffect(() => {
    if (!selectedPatient) {
      loadPatients();
    }
  }, []);

  function handleQueryChange(value) {
    setQuery(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (!value.trim()) {
      loadPatients();
      setShowResults(true);
      return;
    }
    setSearching(true);
    debounceRef.current = setTimeout(async () => {
      try {
        const res = await api.get("/patients", { params: { q: value.trim(), status: "all" } });
        if (res.data && res.data.length > 0) {
          setResults(res.data);
          setShowResults(true);
        } else {
          // fallback: user may have typed an encounter MRN — try searching encounter by MRN
          try {
            const encRes = await api.get(`/encounters/by-mrn/${encodeURIComponent(value)}`);
            const enc = encRes.data;
            if (enc && enc.patient) {
              // auto-select the patient + encounter so billing can proceed using MRN
              const patient = enc.patient;
              setSelectedPatient(patient);
              setEncounters([enc]);
              setSelectedEncounterId(String(enc.id));
              setShowResults(false);
              // notify parent immediately
              if (onSelect) onSelect({
                patientId: patient.id,
                patientLabel: `${patient.full_name} (${patient.patient_uid})`,
                encounterId: enc.id,
                encounterLabel: enc.mrn,
              });
            } else {
              setResults([]);
              setShowResults(true);
            }
          } catch (err) {
            // no encounter found by MRN — show empty patient list
            setResults([]);
            setShowResults(true);
          }
        }
      } catch {
        setResults([]);
        setShowResults(true);
      } finally {
        setSearching(false);
      }
    }, 300);
  }

  async function pickPatient(patient) {
    setSelectedPatient(patient);
    setShowResults(false);
    setQuery("");

    if (!requireEncounter) {
      onSelect({ patientId: patient.id, patientLabel: `${patient.full_name} (${patient.patient_uid})` });
      return;
    }

    setLoadingEncounters(true);
    setEncounterLookupError(false);
    try {
      const res = await api.get(`/patients/${patient.id}/history`);
      const active = res.data.filter((e) => !CLOSED_STAGES.includes(e.stage));
      setEncounters(active);
      if (active.length === 1) {
        setSelectedEncounterId(String(active[0].id));
        onSelect({
          patientId: patient.id,
          patientLabel: `${patient.full_name} (${patient.patient_uid})`,
          encounterId: active[0].id,
          encounterLabel: active[0].mrn,
        });
      } else {
        onSelect({ patientId: patient.id, patientLabel: `${patient.full_name} (${patient.patient_uid})`, encounterId: null });
      }
    } catch {
      setEncounterLookupError(true);
      onSelect({ patientId: patient.id, patientLabel: `${patient.full_name} (${patient.patient_uid})`, encounterId: null });
    } finally {
      setLoadingEncounters(false);
    }
  }

  function pickEncounter(encounterId) {
    setSelectedEncounterId(encounterId);
    const enc = encounters.find((e) => String(e.id) === String(encounterId));
    onSelect({
      patientId: selectedPatient.id,
      patientLabel: `${selectedPatient.full_name} (${selectedPatient.patient_uid})`,
      encounterId: enc ? enc.id : null,
      encounterLabel: enc ? enc.mrn : null,
    });
  }

  function reset() {
    setSelectedPatient(null);
    setEncounters([]);
    setSelectedEncounterId("");
    setQuery("");
    setResults([]);
    if (onClear) onClear();
    onSelect({ patientId: null, encounterId: null });
  }

  if (selectedPatient) {
    return (
      <div className="space-y-2">
        {label && <span className="text-sm font-medium text-ink/80 block">{label}</span>}
        <div className="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 dark:bg-teal-500/10 px-3 py-2">
          <User size={15} className="text-teal-600 dark:text-teal-300 shrink-0" />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold truncate">{selectedPatient.full_name}</p>
            <p className="text-xs text-ink/50 mrn-mono">{selectedPatient.patient_uid}</p>
          </div>
          <button type="button" onClick={reset} className="text-ink/40 hover:text-alert shrink-0" aria-label="Clear patient selection">
            <X size={16} />
          </button>
        </div>

        {requireEncounter && (
          loadingEncounters ? (
            <p className="text-xs text-ink/40">Looking up this patient's open visits…</p>
          ) : encounterLookupError ? (
            <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">
              Couldn't check this patient's open visits — please retry before assuming they have none (re-registering could create a duplicate visit).
            </p>
          ) : encounters.length === 0 ? (
            <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">
              This patient has no open visit right now — they'll need to be registered at reception first.
            </p>
          ) : encounters.length > 1 ? (
            <Select value={selectedEncounterId} onChange={(e) => pickEncounter(e.target.value)}>
              <option value="">Select which visit…</option>
              {encounters.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.mrn} — {e.chief_complaint || "no complaint noted"} ({e.current_department})
                </option>
              ))}
            </Select>
          ) : (
            <p className="text-xs text-ink/50">
              Visit MRN: <span className="mrn-mono">{encounters[0].mrn}</span>
            </p>
          )
        )}
      </div>
    );
  }

  return (
    <div className="relative" ref={containerRef}>
      {label && <span className="text-sm font-medium text-ink/80 mb-1 block">{label}</span>}
      <div className="relative">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
        <Input
          value={query}
          onChange={(e) => handleQueryChange(e.target.value)}
          onFocus={() => {
            if (!query.trim()) {
              loadPatients();
            }
            setShowResults(true);
          }}
          placeholder="Search patient by name or ID…"
          className="pl-9"
        />
      </div>

      {showResults && (
        <div className="absolute z-20 mt-1 w-full bg-surface border border-line rounded-lg shadow-card max-h-64 overflow-y-auto">
          {searching ? (
            <p className="text-xs text-ink/40 px-3 py-3">Searching…</p>
          ) : results.length === 0 ? (
            <div className="px-3 py-3 space-y-2">
              {fetchError ? (
                <>
                  <p className="text-xs text-alert">Couldn't load patients — check network or permissions.</p>
                  <button
                    type="button"
                    onClick={() => loadPatients()}
                    className="text-sm text-teal-700 font-medium"
                  >
                    Retry
                  </button>
                </>
              ) : (
                <>
                  <p className="text-xs text-ink/40">No matching patients.</p>
                  {!query.trim() && (
                    <button
                      type="button"
                      onClick={() => loadPatients()}
                      className="text-sm text-teal-700 font-medium"
                    >
                      Refresh patient list
                    </button>
                  )}
                </>
              )}
            </div>
          ) : (
            results.map((p) => (
              <button
                type="button"
                key={p.id}
                onClick={() => pickPatient(p)}
                className="w-full text-left px-3 py-2 hover:bg-surface-alt transition-colors flex items-center justify-between gap-2"
              >
                <div className="min-w-0">
                  <p className="text-sm font-medium truncate">{p.full_name}</p>
                  <p className="text-xs text-ink/50 mrn-mono">{p.patient_uid} · {calcAge(p.date_of_birth, p.estimated_age) ?? "—"}y</p>
                </div>
                {p.has_active_encounter ? (
                  <Badge tone="success">active visit</Badge>
                ) : (
                  <Badge tone="muted">no open visit</Badge>
                )}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}
