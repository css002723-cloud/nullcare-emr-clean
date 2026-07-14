export default function PageHeader({ icon: Icon, title, subtitle, action }) {
  return (
    <div className="flex items-start justify-between flex-wrap gap-3">
      <div className="flex items-center gap-2.5">
        {Icon && (
          <div className="h-10 w-10 rounded-xl bg-teal-500/10 flex items-center justify-center text-teal-500 shrink-0">
            <Icon size={19} strokeWidth={2} />
          </div>
        )}
        <div>
          <p className="font-display text-2xl">{title}</p>
          {subtitle && <p className="text-sm text-ink/50">{subtitle}</p>}
        </div>
      </div>
      {action}
    </div>
  );
}
