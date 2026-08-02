import { useEffect, useState } from "react";
import {
  UserRound,
  Shield,
  KeyRound,
  Eye,
  EyeOff,
  Save,
  CheckCircle2,
  AlertCircle,
  Phone,
  Mail,
  Building2,
  BadgeCheck,
  LogOut,
} from "lucide-react";
import api from "../services/api";
import { useAuth } from "../context/AuthContext";
import { Button, Card, Field, Input, Badge } from "../components/ui";
import PageHeader from "../components/PageHeader";

const roleLabels = {
  admin: "Administrator",
  reception: "Reception",
  nurse: "Nurse",
  doctor: "Doctor",
  lab_tech: "Laboratory Technician",
  radiologist: "Radiologist",
  pharmacist: "Pharmacist",
  billing: "Billing Officer",
  dialysis_tech: "Dialysis Technician",
  records_officer: "Records Officer",
};

export default function Settings() {
  const { user, refreshUser, logout } = useAuth();

  const [profile, setProfile] = useState({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
  });

  const [profileSaving, setProfileSaving] = useState(false);
  const [profileMessage, setProfileMessage] = useState({
    type: "",
    text: "",
  });

  const [passwords, setPasswords] = useState({
    current_password: "",
    new_password: "",
    confirm_password: "",
  });

  const [passwordSaving, setPasswordSaving] = useState(false);
  const [passwordMessage, setPasswordMessage] = useState({
    type: "",
    text: "",
  });

  const [showPasswords, setShowPasswords] = useState({
    current: false,
    new: false,
    confirm: false,
  });

  useEffect(() => {
    if (!user) return;

    setProfile({
      first_name: user.first_name || "",
      last_name: user.last_name || "",
      email: user.email || "",
      phone: user.phone || "",
    });
  }, [user]);

  function updateProfileField(name, value) {
    setProfile((current) => ({
      ...current,
      [name]: value,
    }));

    setProfileMessage({
      type: "",
      text: "",
    });
  }

  function updatePasswordField(name, value) {
    setPasswords((current) => ({
      ...current,
      [name]: value,
    }));

    setPasswordMessage({
      type: "",
      text: "",
    });
  }

  async function saveProfile(e) {
    e.preventDefault();

    setProfileMessage({
      type: "",
      text: "",
    });

    if (
      !profile.first_name.trim() ||
      !profile.last_name.trim() ||
      !profile.email.trim()
    ) {
      setProfileMessage({
        type: "error",
        text: "First name, last name and email are required.",
      });
      return;
    }

    setProfileSaving(true);

    try {
      const res = await api.put("/auth/profile", {
        first_name: profile.first_name.trim(),
        last_name: profile.last_name.trim(),
        email: profile.email.trim(),
        phone: profile.phone.trim(),
      });

      /*
       * The backend returns a fresh token because the user's
       * full name is stored inside the JWT claims.
       */
      if (res.data.access_token) {
        localStorage.setItem(
          "nullcare_token",
          res.data.access_token
        );
      }

      if (res.data.user) {
        localStorage.setItem(
          "nullcare_user",
          JSON.stringify(res.data.user)
        );
      }

      await refreshUser();

      setProfileMessage({
        type: "success",
        text: "Your profile was updated successfully.",
      });
    } catch (err) {
      setProfileMessage({
        type: "error",
        text:
          err.response?.data?.message ||
          "Could not update your profile. Please try again.",
      });
    } finally {
      setProfileSaving(false);
    }
  }

  async function changePassword(e) {
    e.preventDefault();

    setPasswordMessage({
      type: "",
      text: "",
    });

    const {
      current_password,
      new_password,
      confirm_password,
    } = passwords;

    if (!current_password) {
      setPasswordMessage({
        type: "error",
        text: "Enter your current password.",
      });
      return;
    }

    if (new_password.length < 6) {
      setPasswordMessage({
        type: "error",
        text: "New password must be at least 6 characters.",
      });
      return;
    }

    if (new_password !== confirm_password) {
      setPasswordMessage({
        type: "error",
        text: "New passwords do not match.",
      });
      return;
    }

    if (current_password === new_password) {
      setPasswordMessage({
        type: "error",
        text:
          "Your new password must be different from your current password.",
      });
      return;
    }

    setPasswordSaving(true);

    try {
      await api.post("/auth/change-password-self", {
        current_password,
        new_password,
      });

      setPasswords({
        current_password: "",
        new_password: "",
        confirm_password: "",
      });

      setPasswordMessage({
        type: "success",
        text: "Your password was changed successfully.",
      });

      await refreshUser();
    } catch (err) {
      setPasswordMessage({
        type: "error",
        text:
          err.response?.data?.message ||
          "Could not change your password. Please try again.",
      });
    } finally {
      setPasswordSaving(false);
    }
  }

  const accountStatus = user?.is_active ? "Active" : "Disabled";
  const roleLabel = roleLabels[user?.role] || user?.role || "User";

  return (
    <div className="space-y-6">
      <PageHeader
        icon={UserRound}
        title="Settings"
        subtitle="Manage your personal information, account details and password."
      />

      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.8fr)] gap-5 items-start">
        <div className="space-y-5">

          {/* PERSONAL INFORMATION */}
          <Card>
            <div className="flex items-start gap-3 mb-5">
              <div className="h-10 w-10 rounded-xl bg-teal-500/10 flex items-center justify-center text-teal-500 shrink-0">
                <UserRound size={19} />
              </div>

              <div>
                <h2 className="font-display text-lg">
                  Personal information
                </h2>

                <p className="text-sm text-ink/50">
                  Update the contact information linked to your NullCare account.
                </p>
              </div>
            </div>

            <form onSubmit={saveProfile} className="space-y-5">

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="First name" required>
                  <Input
                    value={profile.first_name}
                    onChange={(e) =>
                      updateProfileField(
                        "first_name",
                        e.target.value
                      )
                    }
                    autoComplete="given-name"
                  />
                </Field>

                <Field label="Last name" required>
                  <Input
                    value={profile.last_name}
                    onChange={(e) =>
                      updateProfileField(
                        "last_name",
                        e.target.value
                      )
                    }
                    autoComplete="family-name"
                  />
                </Field>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <Field label="Email address" required>
                  <div className="relative">
                    <Mail
                      size={16}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none"
                    />

                    <Input
                      type="email"
                      className="pl-9"
                      value={profile.email}
                      onChange={(e) =>
                        updateProfileField(
                          "email",
                          e.target.value
                        )
                      }
                      autoComplete="email"
                    />
                  </div>
                </Field>

                <Field
                  label="Phone number"
                  hint="Optional"
                >
                  <div className="relative">
                    <Phone
                      size={16}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none"
                    />

                    <Input
                      type="tel"
                      className="pl-9"
                      value={profile.phone}
                      onChange={(e) =>
                        updateProfileField(
                          "phone",
                          e.target.value
                        )
                      }
                      autoComplete="tel"
                    />
                  </div>
                </Field>

              </div>

              {profileMessage.text && (
                <div
                  role="alert"
                  className={`flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm ${
                    profileMessage.type === "success"
                      ? "border-moss/25 bg-moss/5 text-moss"
                      : "border-alert/20 bg-alert/5 text-alert"
                  }`}
                >
                  {profileMessage.type === "success" ? (
                    <CheckCircle2 size={17} />
                  ) : (
                    <AlertCircle size={17} />
                  )}

                  <span>{profileMessage.text}</span>
                </div>
              )}

              <div className="flex justify-end">
                <Button
                  type="submit"
                  icon={Save}
                  disabled={profileSaving}
                >
                  {profileSaving
                    ? "Saving…"
                    : "Save profile"}
                </Button>
              </div>

            </form>
          </Card>

          {/* PASSWORD */}
          <Card>
            <div className="flex items-start gap-3 mb-5">

              <div className="h-10 w-10 rounded-xl bg-teal-500/10 flex items-center justify-center text-teal-500 shrink-0">
                <KeyRound size={19} />
              </div>

              <div>
                <h2 className="font-display text-lg">
                  Change password
                </h2>

                <p className="text-sm text-ink/50">
                  Use your current password to set a new one.
                </p>
              </div>

            </div>

            <form
              onSubmit={changePassword}
              className="space-y-4"
            >

              <Field
                label="Current password"
                required
              >
                <div className="relative">

                  <Input
                    type={
                      showPasswords.current
                        ? "text"
                        : "password"
                    }
                    className="pr-10"
                    value={passwords.current_password}
                    onChange={(e) =>
                      updatePasswordField(
                        "current_password",
                        e.target.value
                      )
                    }
                    autoComplete="current-password"
                  />

                  <button
                    type="button"
                    onClick={() =>
                      setShowPasswords((s) => ({
                        ...s,
                        current: !s.current,
                      }))
                    }
                    className="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 flex items-center justify-center text-ink/40 hover:text-ink/70"
                  >
                    {showPasswords.current ? (
                      <EyeOff size={16} />
                    ) : (
                      <Eye size={16} />
                    )}
                  </button>

                </div>
              </Field>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <Field
                  label="New password"
                  required
                  hint="At least 6 characters."
                >
                  <div className="relative">

                    <Input
                      type={
                        showPasswords.new
                          ? "text"
                          : "password"
                      }
                      className="pr-10"
                      value={passwords.new_password}
                      onChange={(e) =>
                        updatePasswordField(
                          "new_password",
                          e.target.value
                        )
                      }
                      autoComplete="new-password"
                    />

                    <button
                      type="button"
                      onClick={() =>
                        setShowPasswords((s) => ({
                          ...s,
                          new: !s.new,
                        }))
                      }
                      className="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 flex items-center justify-center text-ink/40 hover:text-ink/70"
                    >
                      {showPasswords.new ? (
                        <EyeOff size={16} />
                      ) : (
                        <Eye size={16} />
                      )}
                    </button>

                  </div>
                </Field>

                <Field
                  label="Confirm new password"
                  required
                >
                  <div className="relative">

                    <Input
                      type={
                        showPasswords.confirm
                          ? "text"
                          : "password"
                      }
                      className="pr-10"
                      value={passwords.confirm_password}
                      onChange={(e) =>
                        updatePasswordField(
                          "confirm_password",
                          e.target.value
                        )
                      }
                      autoComplete="new-password"
                    />

                    <button
                      type="button"
                      onClick={() =>
                        setShowPasswords((s) => ({
                          ...s,
                          confirm: !s.confirm,
                        }))
                      }
                      className="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 flex items-center justify-center text-ink/40 hover:text-ink/70"
                    >
                      {showPasswords.confirm ? (
                        <EyeOff size={16} />
                      ) : (
                        <Eye size={16} />
                      )}
                    </button>

                  </div>
                </Field>

              </div>

              {passwordMessage.text && (
                <div
                  role="alert"
                  className={`flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm ${
                    passwordMessage.type === "success"
                      ? "border-moss/25 bg-moss/5 text-moss"
                      : "border-alert/20 bg-alert/5 text-alert"
                  }`}
                >
                  {passwordMessage.type === "success" ? (
                    <CheckCircle2 size={17} />
                  ) : (
                    <AlertCircle size={17} />
                  )}

                  <span>{passwordMessage.text}</span>
                </div>
              )}

              <div className="flex justify-end">

                <Button
                  type="submit"
                  icon={KeyRound}
                  disabled={passwordSaving}
                >
                  {passwordSaving
                    ? "Updating…"
                    : "Change password"}
                </Button>

              </div>

            </form>
          </Card>

        </div>

        {/* ACCOUNT SUMMARY */}
        <div className="space-y-5 lg:sticky lg:top-5">

          <Card>

            <div className="flex items-center gap-3 mb-5">

              <div className="h-14 w-14 rounded-full bg-teal-500/10 flex items-center justify-center text-teal-700 dark:text-teal-200 text-xl font-bold">
                {user?.first_name?.[0]?.toUpperCase() || "U"}
                {user?.last_name?.[0]?.toUpperCase() || ""}
              </div>

              <div className="min-w-0">
                <p className="font-display text-xl truncate">
                  {user?.full_name}
                </p>

                <p className="text-sm text-ink/50 truncate">
                  @{user?.username}
                </p>
              </div>

            </div>

            <div className="space-y-3 text-sm">

              <div className="flex items-start gap-3">
                <BadgeCheck
                  size={17}
                  className="text-teal-500 mt-0.5 shrink-0"
                />

                <div>
                  <p className="text-xs text-ink/45">
                    Role
                  </p>
                  <p className="font-medium">
                    {roleLabel}
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <Building2
                  size={17}
                  className="text-teal-500 mt-0.5 shrink-0"
                />

                <div>
                  <p className="text-xs text-ink/45">
                    Department
                  </p>

                  <p className="font-medium">
                    {user?.department || "Not assigned"}
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <Shield
                  size={17}
                  className="text-teal-500 mt-0.5 shrink-0"
                />

                <div>
                  <p className="text-xs text-ink/45">
                    Account status
                  </p>

                  <div className="mt-0.5">
                    <Badge
                      tone={
                        user?.is_active
                          ? "success"
                          : "critical"
                      }
                    >
                      {accountStatus}
                    </Badge>
                  </div>
                </div>
              </div>

            </div>

            <div className="border-t border-line mt-5 pt-4 space-y-2 text-xs text-ink/50">

              <div className="flex justify-between gap-4">
                <span>Username</span>

                <span className="font-medium text-ink/70">
                  {user?.username}
                </span>
              </div>

              <div className="flex justify-between gap-4">
                <span>Account created</span>

                <span className="font-medium text-ink/70">
                  {user?.created_at
                    ? new Date(
                        user.created_at
                      ).toLocaleDateString()
                    : "—"}
                </span>
              </div>

              <div className="flex justify-between gap-4">
                <span>Password changed</span>

                <span className="font-medium text-ink/70">
                  {user?.password_changed_at
                    ? new Date(
                        user.password_changed_at
                      ).toLocaleDateString()
                    : "—"}
                </span>
              </div>

            </div>

          </Card>

          {/* SIGN OUT */}
          <Card className="border-alert/20">

            <div className="flex items-start gap-3">

              <div className="h-9 w-9 rounded-lg bg-alert/10 flex items-center justify-center text-alert shrink-0">
                <LogOut size={17} />
              </div>

              <div className="min-w-0 flex-1">

                <h2 className="font-semibold text-sm">
                  Sign out
                </h2>

                <p className="text-xs text-ink/50 mt-1">
                  Sign out of this NullCare session on this device.
                </p>

                <Button
                  type="button"
                  variant="danger"
                  size="sm"
                  className="mt-3"
                  onClick={logout}
                >
                  Sign out
                </Button>

              </div>

            </div>

          </Card>

        </div>
      </div>
    </div>
  );
}