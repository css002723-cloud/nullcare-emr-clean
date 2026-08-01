import { useEffect, useState, useCallback } from "react";
import { MessageSquareText, Send, CheckCircle2 } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Select, Textarea, LoadingRow, EmptyState, priorityTone } from "../components/ui";
import PageHeader from "../components/PageHeader";

const DEPARTMENTS = ["reception", "triage", "consultation", "laboratory", "imaging", "pharmacy", "dialysis", "ward", "billing"];

export default function Messages() {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [replyingTo, setReplyingTo] = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/referrals/inbox")
      .then((res) => setMessages(res.data.messages))
      .catch(() => setMessages([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function markRead(id) {
    await api.post(`/referrals/${id}/read`);
    setMessages((list) => list.map((m) => (m.id === id ? { ...m, is_read: true } : m)));
  }

  return (
    <div className="space-y-5">
      <PageHeader
        icon={MessageSquareText}
        title="Messages"
        subtitle="Patients sent to your department, with a note from whoever sent them — reply to hand them onward or back to the doctor."
      />

      {loading ? <LoadingRow /> : messages.length === 0 ? (
        <EmptyState
          icon={MessageSquareText}
          title="No messages yet"
          hint="When another department refers a patient to you with a note, it'll show up here."
        />
      ) : (
        <div className="space-y-3">
          {messages.map((m) => (
            <MessageCard
              key={m.id}
              message={m}
              onMarkRead={() => markRead(m.id)}
              onReply={() => setReplyingTo(replyingTo === m.id ? null : m.id)}
              replying={replyingTo === m.id}
              onSent={() => { setReplyingTo(null); load(); }}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function MessageCard({ message, onMarkRead, onReply, replying, onSent }) {
  return (
    <Card className={message.is_read ? "" : "border-teal-300 dark:border-teal-500/50"}>
      <div className="flex items-start justify-between gap-3 flex-wrap">
        <div className="flex items-start gap-3 min-w-0">
          {!message.is_read && <span className="mt-1.5 h-2 w-2 rounded-full bg-teal-500 shrink-0" aria-hidden="true" />}
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap mb-1">
              <Badge tone="neutral">from {message.from_department}</Badge>
              <span className="text-xs text-ink/40">→</span>
              <Badge tone="muted">{message.to_department}</Badge>
              <Badge tone={priorityTone(message.priority)}>{message.priority}</Badge>
              {message.status !== "pending" && <Badge tone={message.status === "declined" ? "critical" : "success"}>{message.status}</Badge>}
            </div>
            <p className="text-sm font-semibold">
              {message.patient?.full_name} <span className="text-ink/40 font-normal mrn-mono">({message.patient?.patient_uid})</span>
            </p>
            <p className="text-sm text-ink/70 mt-1">{message.reason || <span className="italic text-ink/40">No message text</span>}</p>
            <p className="text-xs text-ink/40 mt-1.5">
              {message.referred_by_name ? `Sent by ${message.referred_by_name}` : "Sender unknown"} · {new Date(message.created_at).toLocaleString()}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {!message.is_read && (
            <Button size="sm" variant="ghost" icon={CheckCircle2} onClick={onMarkRead}>Mark read</Button>
          )}
          <Button size="sm" variant="secondary" icon={Send} onClick={onReply}>
            {replying ? "Cancel" : "Send onward"}
          </Button>
        </div>
      </div>

      {replying && (
        <ReplyForm encounterId={message.encounter_id} defaultDepartment={message.from_department} onSent={onSent} />
      )}
    </Card>
  );
}

function ReplyForm({ encounterId, defaultDepartment, onSent }) {
  const [toDepartment, setToDepartment] = useState(defaultDepartment || "consultation");
  const [text, setText] = useState("");
  const [priority, setPriority] = useState("routine");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post("/referrals", { encounter_id: encounterId, to_department: toDepartment, reason: text, priority });
      onSent();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't send — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="mt-3 pt-3 border-t border-line space-y-2">
      <div className="grid md:grid-cols-2 gap-2">
        <Select value={toDepartment} onChange={(e) => setToDepartment(e.target.value)}>
          {DEPARTMENTS.map((d) => <option key={d} value={d}>Send to {d}</option>)}
        </Select>
        <Select value={priority} onChange={(e) => setPriority(e.target.value)}>
          <option value="routine">Routine</option>
          <option value="urgent">Urgent</option>
          <option value="emergency">Emergency</option>
        </Select>
      </div>
      <Textarea placeholder="Message for the receiving department…" value={text} onChange={(e) => setText(e.target.value)} />
      {error && <p className="text-sm text-alert">{error}</p>}
      <Button type="submit" size="sm" disabled={saving}>{saving ? "Sending…" : "Send"}</Button>
    </form>
  );
}
