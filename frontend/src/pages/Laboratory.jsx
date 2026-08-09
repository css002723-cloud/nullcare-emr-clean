import { useEffect, useState, useCallback } from "react";
import {
  FlaskConical,
  User,
  FileText,
  Stethoscope,
  ClipboardList,
  AlertTriangle,
} from "lucide-react";

import api from "../services/api";

import {
  Card,
  Badge,
  Button,
  Field,
  Input,
  Select,
  LoadingRow,
  EmptyState,
} from "../components/ui";

import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";
import { useAuth } from "../context/AuthContext";

const STATUS_TABS = [
  "ordered",
  "collected",
  "received",
  "resulted",
  "verified",
];

export default function Laboratory() {
  const { hasRole } = useAuth();

  const [orders, setOrders] = useState([]);
  const [tab, setTab] = useState("ordered");
  const [loading, setLoading] = useState(true);
  const [catalog, setCatalog] = useState({});
  const [actionError, setActionError] = useState("");

  const load = useCallback((status) => {
    setLoading(true);

    api
      .get("/lab/orders", {
        params: status ? { status } : {},
      })
      .then((res) => {
        setOrders(Array.isArray(res.data) ? res.data : []);
      })
      .catch(() => {
        setOrders([]);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  useEffect(() => {
    load(tab);
  }, [tab, load]);

  useEffect(() => {
    api
      .get("/lab/catalog")
      .then((res) => setCatalog(res.data || {}))
      .catch(() => {});
  }, []);

  async function collect(id) {
    setActionError("");

    try {
      await api.post(`/lab/orders/${id}/collect`);
      load(tab);
    } catch (err) {
      setActionError(
        err.response?.data?.message ||
          "Couldn't mark specimen collected — please try again."
      );
    }
  }

  async function receive(id) {
    setActionError("");

    try {
      await api.post(`/lab/orders/${id}/receive`);
      load(tab);
    } catch (err) {
      setActionError(
        err.response?.data?.message ||
          "Couldn't mark specimen received — please try again."
      );
    }
  }

  async function verifyResult(labResultId) {
    setActionError("");

    try {
      await api.post(`/lab/results/${labResultId}/verify`);
      load(tab);
    } catch (err) {
      setActionError(
        err.response?.data?.message ||
          "Couldn't verify the result — please try again."
      );
    }
  }

  async function acknowledgeCritical(labResultId) {
    setActionError("");

    try {
      await api.post(
        `/lab/results/${labResultId}/acknowledge-critical`
      );

      load(tab);
    } catch (err) {
      setActionError(
        err.response?.data?.message ||
          "Couldn't acknowledge the critical result — please try again."
      );
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title="Laboratory"
        subtitle="Laboratory investigations, specimens and results"
        icon={FlaskConical}
      />

      {hasRole("doctor", "nurse") && (
        <QuickOrderPanel
          catalog={catalog}
          onOrdered={() => load(tab)}
        />
      )}

      {/* STATUS TABS */}
      <div className="flex gap-2 flex-wrap">
        {STATUS_TABS.map((s) => (
          <button
            key={s}
            onClick={() => setTab(s)}
            className={`px-3 py-1.5 rounded-full text-sm font-medium capitalize ${
              tab === s
                ? "bg-teal-500 text-white"
                : "bg-surface border border-line text-ink/60"
            }`}
          >
            {s}
          </button>
        ))}
      </div>

      {actionError && (
        <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">
          {actionError}
        </p>
      )}

      {loading ? (
        <LoadingRow />
      ) : orders.length === 0 ? (
        <EmptyState title={`No ${tab} orders`} />
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <LabOrderRow
              key={o.id}
              order={o}
              canVerify={hasRole("lab_tech", "admin")}
              canAcknowledge={hasRole(
                "doctor",
                "nurse",
                "admin"
              )}
              onCollect={() => collect(o.id)}
              onReceive={() => receive(o.id)}
              onResulted={() => load(tab)}
              onVerify={() =>
                o.result?.id && verifyResult(o.result.id)
              }
              onAcknowledge={() =>
                o.result?.id &&
                acknowledgeCritical(o.result.id)
              }
            />
          ))}
        </div>
      )}
    </div>
  );
}


/* ============================================================
   QUICK LAB ORDER
============================================================ */

function QuickOrderPanel({ catalog, onOrdered }) {
  const [encounterId, setEncounterId] = useState(null);
  const [testCode, setTestCode] = useState("");
  const [specimenType, setSpecimenType] = useState("");
  const [priority, setPriority] = useState("routine");

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [resetKey, setResetKey] = useState(0);

  async function submit(e) {
    e.preventDefault();
    setError("");

    if (!encounterId || !testCode) {
      setError(
        "Select a patient's visit and a test before placing the order."
      );
      return;
    }

    setSaving(true);

    try {
      await api.post("/lab/orders", {
        encounter_id: encounterId,
        test_code: testCode,
        specimen_type: specimenType,
        priority,
      });

      setEncounterId(null);
      setTestCode("");
      setSpecimenType("");
      setPriority("routine");

      setResetKey((k) => k + 1);

      onOrdered();
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Couldn't place the order — please try again."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <FlaskConical
          size={18}
          className="text-teal-600"
        />

        <div>
          <h2 className="font-semibold">
            Order a test
          </h2>

          <p className="text-xs text-ink/50">
            LOINC-coded laboratory investigation
          </p>
        </div>
      </div>

      <form
        onSubmit={submit}
        className="grid md:grid-cols-4 gap-3 items-end"
      >
        <div className="md:col-span-2">
          <PatientLookup
            key={resetKey}
            requireEncounter
            label="Patient"
            onSelect={({ encounterId }) =>
              setEncounterId(encounterId)
            }
          />
        </div>

        <Field label="Test">
          <Select
            value={testCode}
            onChange={(e) =>
              setTestCode(e.target.value)
            }
          >
            <option value="">Select…</option>

            {Object.entries(catalog).map(
              ([code, meta]) => (
                <option key={code} value={code}>
                  {meta.loinc_display} (
                  {meta.loinc_code})
                </option>
              )
            )}
          </Select>
        </Field>

        <Field label="Specimen">
          <Input
            value={specimenType}
            onChange={(e) =>
              setSpecimenType(e.target.value)
            }
            placeholder="e.g. venous blood"
          />
        </Field>

        <Field label="Priority">
          <Select
            value={priority}
            onChange={(e) =>
              setPriority(e.target.value)
            }
          >
            <option value="routine">
              Routine
            </option>

            <option value="urgent">
              Urgent
            </option>

            <option value="stat">
              Stat
            </option>
          </Select>
        </Field>

        {error && (
          <p className="text-sm text-alert md:col-span-4">
            {error}
          </p>
        )}

        <Button
          type="submit"
          disabled={saving}
          className="md:col-span-4"
        >
          {saving
            ? "Ordering…"
            : "Place lab order"}
        </Button>
      </form>
    </Card>
  );
}


/* ============================================================
   LAB ORDER CARD
============================================================ */

function LabOrderRow({
  order,
  canVerify,
  canAcknowledge,
  onCollect,
  onReceive,
  onResulted,
  onVerify,
  onAcknowledge,
}) {
  const [showResultForm, setShowResultForm] =
    useState(false);

  const [result, setResult] = useState({
    result_value: "",
    unit: "",
    reference_range: "",
    is_critical: false,
    is_abnormal: false,
    interpretation: "",
  });

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submitResult(e) {
    e.preventDefault();

    setSaving(true);
    setError("");

    try {
      await api.post(
        `/lab/orders/${order.id}/result`,
        result
      );

      setShowResultForm(false);

      onResulted();
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Couldn't save the result — please try again."
      );
    } finally {
      setSaving(false);
    }
  }

  const needsAcknowledgement =
    order.result?.is_critical &&
    !order.result?.critical_alert_acknowledged;


  /* ==========================================================
     PATIENT / ENCOUNTER DATA
  ========================================================== */

  const patient = order.patient || {};
  const encounter = order.encounter || {};


  /* Patient ID */

  const patientId =
    order.patient_id ||
    order.patient_id_display ||
    order.patient_identifier ||
    patient.patient_uid ||
    patient.patient_id ||
    patient.identifier ||
    patient.mrn ||
    "Not available";


  /* Age */

  const age =
    order.age ??
    patient.age ??
    encounter.age ??
    "Not available";


  /* Sex */

  const sex =
    order.sex ||
    patient.sex ||
    patient.gender ||
    "Not available";


  /* Patient category */

  const patientCategory =
    order.patient_category ||
    encounter.patient_category ||
    encounter.visit_type ||
    encounter.category ||
    patient.patient_category ||
    patient.category ||
    "Not specified";


  /* MRN */

  const mrn =
    order.mrn ||
    encounter.mrn ||
    "Not available";


  /* Encounter number */

  const encounterNumber =
    order.encounter_number ||
    encounter.encounter_number ||
    "Not available";


  /* Emergency */

  const isEmergency =
    Boolean(
      order.is_emergency ||
      encounter.is_emergency
    );


  /* Priority */

  const priority =
    order.priority ||
    encounter.priority ||
    "routine";


  /* ==========================================================
     REFERRING DOCTOR
  ========================================================== */

  const referringDoctor =
    order.referring_doctor ||
    order.referring_doctor_name ||
    order.doctor_name ||
    order.doctor?.name ||
    "Not specified";


  /* ==========================================================
     DOCTOR'S NOTES
  ========================================================== */

  const doctorNote =
    order.doctor_note ||
    order.doctor_notes ||
    order.notes ||
    encounter.doctor_note ||
    encounter.doctor_notes ||
    encounter.notes ||
    order.latest_doctor_note?.note ||
    order.latest_doctor_note?.body ||
    order.latest_note?.note ||
    order.latest_note?.body ||
    "";


  return (
    <Card
      className={
        needsAcknowledgement
          ? "border-alert/50 bg-alert/5"
          : ""
      }
    >

      {/* ======================================================
          HEADER
      ======================================================= */}

      <div className="flex items-start justify-between flex-wrap gap-3">

        <div className="min-w-0">

          <div className="flex items-center gap-2 flex-wrap">

            <p className="text-sm font-semibold text-teal-700">
              {order.patient_name ||
                patient.full_name ||
                patient.name ||
                "Patient name unavailable"}
            </p>

            {isEmergency && (
              <Badge tone="critical">
                <span className="flex items-center gap-1">
                  <AlertTriangle size={12} />
                  Emergency
                </span>
              </Badge>
            )}

            {priority !== "routine" &&
              !isEmergency && (
                <Badge tone="muted">
                  {priority}
                </Badge>
              )}
          </div>

          <p className="font-semibold mt-1">
            {order.loinc_display ||
              order.test_code}
          </p>

          <p className="text-xs text-ink/50 mrn-mono mt-1">
            LOINC{" "}
            {order.loinc_code || "n/a"} ·
            barcode{" "}
            {order.barcode || "n/a"}
          </p>
        </div>


        {/* ACTIONS */}

        <div className="flex items-center gap-2 flex-wrap">

          <Badge tone="muted">
            {order.status}
          </Badge>

          {order.status === "ordered" && (
            <Button
              size="sm"
              onClick={onCollect}
            >
              Mark specimen collected
            </Button>
          )}

          {order.status === "collected" && (
            <Button
              size="sm"
              onClick={onReceive}
            >
              Mark received in lab
            </Button>
          )}

          {order.status === "received" &&
            !showResultForm && (
              <Button
                size="sm"
                onClick={() =>
                  setShowResultForm(true)
                }
              >
                Enter result
              </Button>
            )}

          {order.status === "resulted" &&
            canVerify &&
            order.result?.id && (
              <Button
                size="sm"
                onClick={onVerify}
              >
                Verify result
              </Button>
            )}
        </div>
      </div>


      {/* ======================================================
          PATIENT INFORMATION
      ======================================================= */}

      <div className="mt-4 border-t border-line pt-4">

        <div className="flex items-center gap-2 mb-3">

          <User
            size={17}
            className="text-teal-600"
          />

          <h3 className="text-sm font-semibold">
            Patient Information
          </h3>
        </div>


        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">

          {/* PATIENT ID */}

          <InfoBox
            label="Patient ID"
            value={patientId}
            mono
          />


          {/* AGE */}

          <InfoBox
            label="Age"
            value={
              age !== "Not available"
                ? `${age} ${
                    /^\d+$/.test(
                      String(age)
                    )
                      ? "years"
                      : ""
                  }`
                : age
            }
          />


          {/* SEX */}

          <InfoBox
            label="Sex"
            value={sex}
            capitalize
          />


          {/* CATEGORY */}

          <InfoBox
            label="Patient Category"
            value={String(
              patientCategory
            ).replace(/_/g, " ")}
            capitalize
          />


          {/* MRN */}

          <InfoBox
            label="MRN / Visit"
            value={mrn}
            mono
          />


          {/* ENCOUNTER */}

          <InfoBox
            label="Encounter"
            value={encounterNumber}
            mono
          />


          {/* PRIORITY */}

          <InfoBox
            label="Priority"
            value={priority}
            capitalize
          />


          {/* VISIT TYPE */}

          <InfoBox
            label="Visit Type"
            value={
              String(
                order.visit_type ||
                  encounter.visit_type ||
                  patientCategory
              ).replace(
                /_/g,
                " "
              )
            }
            capitalize
          />
        </div>
      </div>


      {/* ======================================================
          REFERRING DOCTOR
      ======================================================= */}

      <div className="mt-4 border-t border-line pt-4">

        <div className="flex items-center gap-2 mb-3">

          <Stethoscope
            size={17}
            className="text-teal-600"
          />

          <h3 className="text-sm font-semibold">
            Referring Doctor
          </h3>
        </div>


        <div className="rounded-lg bg-surface-alt border border-line p-3">

          <div className="flex items-center gap-3">

            <div className="h-9 w-9 rounded-full bg-teal-500/10 flex items-center justify-center">

              <Stethoscope
                size={17}
                className="text-teal-600"
              />

            </div>

            <div>

              <p className="text-sm font-semibold">

                {referringDoctor !==
                "Not specified"
                  ? referringDoctor
                  : "Not specified"}

              </p>

              {order.referring_doctor_id && (
                <p className="text-xs text-ink/45">
                  Doctor
                  {}
                </p>
              )}

            </div>
          </div>
        </div>
      </div>


      {/* ======================================================
          DOCTOR'S NOTES
      ======================================================= */}

      <div className="mt-4 border-t border-line pt-4">

        <div className="flex items-center gap-2 mb-3">

          <FileText
            size={17}
            className="text-teal-600"
          />

          <h3 className="text-sm font-semibold">
            Doctor's Notes
          </h3>
        </div>


        {doctorNote ? (

          <div className="rounded-lg bg-surface-alt border border-line p-3">

            <div className="flex items-start gap-3">

              <FileText
                size={18}
                className="text-teal-600 mt-0.5 shrink-0"
              />

              <div className="min-w-0 flex-1">

                {referringDoctor !==
                  "Not specified" && (
                  <p className="text-xs font-semibold text-teal-700 mb-2">
                    {referringDoctor}
                  </p>
                )}

                <p className="text-sm text-ink/75 whitespace-pre-wrap leading-relaxed">
                  {doctorNote}
                </p>

              </div>
            </div>
          </div>

        ) : (

          <div className="rounded-lg bg-surface-alt border border-line p-3">

            <div className="flex items-center gap-2 text-ink/50">

              <FileText size={16} />

              <p className="text-sm">
                No doctor's notes available
                for this laboratory order.
              </p>

            </div>

          </div>
        )}
      </div>


      {/* ======================================================
          LAB RESULT
      ======================================================= */}

      {order.result && (

        <div
          className={`mt-4 text-sm rounded-lg p-3 ${
            needsAcknowledgement
              ? "bg-alert/10 border border-alert/30"
              : "bg-surface-alt"
          }`}
        >

          <div className="flex items-center justify-between flex-wrap gap-2">

            <div>

              <span className="text-ink/60">
                Result:
              </span>{" "}

              <span className="font-semibold">

                {order.result.result_value}{" "}

                {order.result.unit}

              </span>


              {order.result.reference_range && (

                <span className="text-ink/50">

                  {" "}
                  (ref:{" "}
                  {order.result.reference_range})

                </span>
              )}


              {order.result.is_abnormal && (
                <Badge
                  tone="muted"
                  className="ml-2"
                >
                  Abnormal
                </Badge>
              )}


              {order.result.is_critical && (

                <Badge
                  tone="critical"
                  className="ml-2"
                >
                  {order.result
                    .critical_alert_acknowledged
                    ? "Critical — acknowledged"
                    : "Critical — needs acknowledgement"}
                </Badge>
              )}

            </div>


            {needsAcknowledgement &&
              canAcknowledge && (

                <Button
                  size="sm"
                  variant="clay"
                  onClick={onAcknowledge}
                >
                  Acknowledge critical result
                </Button>
              )}
          </div>


          {order.result.interpretation && (

            <div className="mt-2 pt-2 border-t border-line">

              <span className="text-xs uppercase tracking-wide text-ink/40">
                Interpretation
              </span>

              <p className="mt-1 text-sm text-ink/75">
                {order.result.interpretation}
              </p>

            </div>
          )}
        </div>
      )}


      {/* ======================================================
          RESULT ENTRY FORM
      ======================================================= */}

      {showResultForm && (

        <form
          onSubmit={submitResult}
          className="mt-4 grid md:grid-cols-2 gap-3 border-t border-line pt-4"
        >

          <div className="md:col-span-2 flex items-center gap-2">

            <ClipboardList
              size={17}
              className="text-teal-600"
            />

            <h3 className="text-sm font-semibold">
              Enter Laboratory Result
            </h3>

          </div>


          <Field
            label="Result value"
            required
          >
            <Input
              value={
                result.result_value
              }
              onChange={(e) =>
                setResult({
                  ...result,
                  result_value:
                    e.target.value,
                })
              }
            />
          </Field>


          <Field label="Unit">

            <Input
              value={result.unit}
              onChange={(e) =>
                setResult({
                  ...result,
                  unit: e.target.value,
                })
              }
            />

          </Field>


          <Field label="Reference range">

            <Input
              value={
                result.reference_range
              }
              onChange={(e) =>
                setResult({
                  ...result,
                  reference_range:
                    e.target.value,
                })
              }
            />

          </Field>


          <Field label="Interpretation">

            <Input
              value={
                result.interpretation
              }
              onChange={(e) =>
                setResult({
                  ...result,
                  interpretation:
                    e.target.value,
                })
              }
            />

          </Field>


          <label className="flex items-center gap-2 text-sm">

            <input
              type="checkbox"
              checked={
                result.is_abnormal
              }
              onChange={(e) =>
                setResult({
                  ...result,
                  is_abnormal:
                    e.target.checked,
                })
              }
            />

            Abnormal

          </label>


          <label className="flex items-center gap-2 text-sm text-alert">

            <input
              type="checkbox"
              checked={
                result.is_critical
              }
              onChange={(e) =>
                setResult({
                  ...result,
                  is_critical:
                    e.target.checked,
                })
              }
            />

            Critical value —
            triggers alert

          </label>


          {error && (

            <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1 md:col-span-2">
              {error}
            </p>

          )}


          <div className="md:col-span-2 flex gap-2">

            <Button
              type="submit"
              disabled={saving}
            >
              {saving
                ? "Saving…"
                : "Save result"}
            </Button>

            <Button
              type="button"
              variant="ghost"
              onClick={() =>
                setShowResultForm(false)
              }
              disabled={saving}
            >
              Cancel
            </Button>

          </div>

        </form>
      )}

    </Card>
  );
}


/* ============================================================
   INFORMATION BOX
============================================================ */

function InfoBox({
  label,
  value,
  mono = false,
  capitalize = false,
}) {
  return (
    <div className="rounded-lg bg-surface-alt border border-line p-3">

      <p className="text-[11px] uppercase tracking-wide text-ink/40 mb-1">
        {label}
      </p>

      <p
        className={`text-sm font-semibold ${
          mono ? "mrn-mono break-all" : ""
        } ${
          capitalize ? "capitalize" : ""
        }`}
      >
        {value || "Not available"}
      </p>

    </div>
  );
}