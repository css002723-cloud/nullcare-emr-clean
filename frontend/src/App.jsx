import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider } from "./context/AuthContext";
import { ThemeProvider } from "./context/ThemeContext";
import ProtectedRoute from "./components/ProtectedRoute";

import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Patients from "./pages/Patients";
import PatientDetail from "./pages/PatientDetail";
import Reception from "./pages/Reception";
import Triage from "./pages/Triage";
import Consultation from "./pages/Consultation";
import EncounterWorkspace from "./pages/EncounterWorkspace";
import Laboratory from "./pages/Laboratory";
import Imaging from "./pages/Imaging";
import Pharmacy from "./pages/Pharmacy";
import Wards from "./pages/Wards";
import Dialysis from "./pages/Dialysis";
import Billing from "./pages/Billing";
import Research from "./pages/Research";
import AdminUsers from "./pages/AdminUsers";
import AdminAudit from "./pages/AdminAudit";

export default function App() {
  return (
    <BrowserRouter>
      <ThemeProvider>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />

            <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />

            <Route path="/patients" element={<ProtectedRoute><Patients /></ProtectedRoute>} />
            <Route path="/patients/:id" element={<ProtectedRoute><PatientDetail /></ProtectedRoute>} />

            <Route path="/reception" element={<ProtectedRoute roles={["reception", "nurse"]}><Reception /></ProtectedRoute>} />
            <Route path="/triage" element={<ProtectedRoute roles={["nurse", "doctor"]}><Triage /></ProtectedRoute>} />
            <Route path="/consultation" element={<ProtectedRoute roles={["doctor"]}><Consultation /></ProtectedRoute>} />
            <Route path="/encounters/:id" element={<ProtectedRoute><EncounterWorkspace /></ProtectedRoute>} />

            <Route path="/laboratory" element={<ProtectedRoute roles={["lab_tech", "doctor", "nurse"]}><Laboratory /></ProtectedRoute>} />
            <Route path="/imaging" element={<ProtectedRoute roles={["radiologist", "doctor"]}><Imaging /></ProtectedRoute>} />
            <Route path="/pharmacy" element={<ProtectedRoute roles={["pharmacist", "doctor"]}><Pharmacy /></ProtectedRoute>} />
            <Route path="/wards" element={<ProtectedRoute roles={["nurse", "doctor"]}><Wards /></ProtectedRoute>} />
            <Route path="/dialysis" element={<ProtectedRoute roles={["dialysis_tech", "doctor", "nurse"]}><Dialysis /></ProtectedRoute>} />
            <Route path="/billing" element={<ProtectedRoute roles={["billing"]}><Billing /></ProtectedRoute>} />
            <Route path="/research" element={<ProtectedRoute roles={["records_officer"]}><Research /></ProtectedRoute>} />

            <Route path="/admin/users" element={<ProtectedRoute roles={["admin"]}><AdminUsers /></ProtectedRoute>} />
            <Route path="/admin/audit" element={<ProtectedRoute roles={["admin"]}><AdminAudit /></ProtectedRoute>} />

            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </AuthProvider>
      </ThemeProvider>
    </BrowserRouter>
  );
}
