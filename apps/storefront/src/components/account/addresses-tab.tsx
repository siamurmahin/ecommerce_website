"use client";

import { Loader2, MapPin, Plus } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { Field, inputClass, selectClass, selectStyle } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useSetCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { deleteAddress, saveAddress } from "@/lib/api/customer";
import type { CheckoutOptions, Customer, CustomerAddress } from "@/lib/api/types";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

type Draft = { label: string; name: string; phone: string; district_id: string; thana_id: string; address: string; is_default: boolean };

export function AddressesTab({ customer, options }: { customer: Customer; options: CheckoutOptions }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const setCustomer = useSetCustomer();
  const [editing, setEditing] = useState<CustomerAddress | "new" | null>(null);

  const replace = (addresses: CustomerAddress[]) => setCustomer({ ...customer, addresses });

  const remove = async (address: CustomerAddress) => {
    if (!confirm(t("confirmDelete"))) return;
    await deleteAddress(address.id, locale);
    // The backend promotes another address to default; re-read rather than guess which.
    const rest = customer.addresses.filter((a) => a.id !== address.id);
    replace(address.is_default && rest.length > 0 ? rest.map((a, i) => ({ ...a, is_default: i === 0 })) : rest);
  };

  if (editing) {
    return (
      <AddressForm
        address={editing === "new" ? null : editing}
        customer={customer}
        options={options}
        onCancel={() => setEditing(null)}
        onSaved={(saved) => {
          const others = customer.addresses.filter((a) => a.id !== saved.id).map((a) => (saved.is_default ? { ...a, is_default: false } : a));
          replace(saved.is_default ? [saved, ...others] : [...others, saved]);
          setEditing(null);
        }}
      />
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {customer.addresses.length === 0 && <p className="rounded-lg bg-card px-6 py-10 text-center text-muted-foreground">{t("noAddresses")}</p>}
      <ul className="grid gap-3 sm:grid-cols-2">
        {customer.addresses.map((a) => (
          <li key={a.id} className={cn("flex flex-col gap-3 rounded-lg bg-card p-5", a.is_default && "ring-2 ring-primary/30")}>
            <div className="flex items-center gap-2">
              <MapPin className="size-4 text-primary" strokeWidth={1.8} />
              <span className="font-semibold">{a.label || a.name}</span>
              {a.is_default && <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-primary">{t("default")}</span>}
            </div>
            <p className="text-sm leading-relaxed text-muted-foreground">
              {a.name} · <span className="tabular-nums">{a.phone}</span>
              <br />
              {a.address}
              <br />
              {[a.thana, a.district].filter(Boolean).join(", ")}
            </p>
            <div className="mt-auto flex gap-4 text-sm font-semibold">
              <button type="button" onClick={() => setEditing(a)} className="text-primary hover:underline">
                {t("edit")}
              </button>
              <button type="button" onClick={() => remove(a)} className="text-muted-foreground hover:text-destructive">
                {t("delete")}
              </button>
            </div>
          </li>
        ))}
      </ul>
      {customer.addresses.length < 10 && (
        <button type="button" onClick={() => setEditing("new")} className={cn(buttonVariants({ variant: "outline" }), "h-11 self-start rounded-full px-5")}>
          <Plus className="size-4" />
          {t("addAddress")}
        </button>
      )}
    </div>
  );
}

function AddressForm({ address, customer, options, onCancel, onSaved }: {
  address: CustomerAddress | null;
  customer: Customer;
  options: CheckoutOptions;
  onCancel: () => void;
  onSaved: (address: CustomerAddress) => void;
}) {
  const t = useTranslations("account");
  const tc = useTranslations("checkout");
  const locale = useLocale();
  const [draft, setDraft] = useState<Draft>({
    label: address?.label ?? "",
    name: address?.name ?? customer.name ?? "",
    phone: address?.phone ?? customer.phone,
    district_id: address ? String(address.district_id) : "",
    thana_id: address?.thana_id ? String(address.thana_id) : "",
    address: address?.address ?? "",
    is_default: address?.is_default ?? customer.addresses.length === 0,
  });
  const [errors, setErrors] = useState<Partial<Record<keyof Draft | "form", string>>>({});
  const [busy, setBusy] = useState(false);
  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => setDraft((d) => ({ ...d, [key]: value }));
  const thanas = options.divisions.flatMap((d) => d.districts).find((d) => d.id === Number(draft.district_id))?.thanas ?? [];

  const submit = async () => {
    const next: typeof errors = {};
    if (draft.name.trim().length < 2) next.name = t("errors.name");
    if (!BD_PHONE.test(normalizePhone(draft.phone))) next.phone = t("errors.phone");
    if (!draft.district_id) next.district_id = t("errors.district");
    if (thanas.length > 0 && !draft.thana_id) next.thana_id = tc("errors.thana");
    if (draft.address.trim().length < 8) next.address = t("errors.address");
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setBusy(true);
    try {
      const saved = await saveAddress(
        {
          label: draft.label.trim() || null,
          name: draft.name.trim(),
          phone: normalizePhone(draft.phone),
          district_id: Number(draft.district_id),
          thana_id: draft.thana_id ? Number(draft.thana_id) : null,
          address: draft.address.trim(),
          is_default: draft.is_default,
        },
        address?.id ?? null,
        locale,
      );
      onSaved(saved);
    } catch (e) {
      setErrors({ form: e instanceof ApiError && e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? t("errors.generic")) : t("errors.generic") });
    } finally {
      setBusy(false);
    }
  };

  return (
    <form
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        submit();
      }}
      className="flex flex-col gap-4 rounded-lg bg-card p-5 sm:p-6"
    >
      <h2 className="font-heading text-xl font-semibold">{address ? t("editAddress") : t("addAddress")}</h2>
      <Field label={t("label")}>{(props) => <input {...props} value={draft.label} onChange={(e) => set("label", e.target.value)} maxLength={40} className={inputClass} />}</Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t("recipient")} error={errors.name}>
          {(props) => <input {...props} value={draft.name} onChange={(e) => set("name", e.target.value)} autoComplete="name" className={inputClass} />}
        </Field>
        <Field label={t("recipientPhone")} error={errors.phone}>
          {(props) => <input {...props} value={draft.phone} onChange={(e) => set("phone", e.target.value)} type="tel" inputMode="tel" className={cn(inputClass, "tabular-nums")} />}
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={tc("district")} error={errors.district_id}>
          {(props) => (
            <select {...props} value={draft.district_id} onChange={(e) => setDraft((d) => ({ ...d, district_id: e.target.value, thana_id: "" }))} className={selectClass} style={selectStyle}>
              <option value="">{tc("districtPlaceholder")}</option>
              {options.divisions.map((division) => (
                <optgroup key={division.name} label={division.name}>
                  {division.districts.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </optgroup>
              ))}
            </select>
          )}
        </Field>
        {thanas.length > 0 && (
          <Field label={tc("thana")} error={errors.thana_id}>
            {(props) => (
              <select {...props} value={draft.thana_id} onChange={(e) => set("thana_id", e.target.value)} className={selectClass} style={selectStyle}>
                <option value="">{tc("thanaPlaceholder")}</option>
                {thanas.map((th) => (
                  <option key={th.id} value={th.id}>
                    {th.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
        )}
      </div>
      <Field label={tc("fullAddress")} error={errors.address}>
        {(props) => (
          <textarea {...props} value={draft.address} onChange={(e) => set("address", e.target.value)} rows={3} placeholder={tc("fullAddressPlaceholder")} className={cn(inputClass, "h-auto resize-none py-3")} />
        )}
      </Field>
      <label className="flex items-center gap-3 text-sm">
        <input type="checkbox" checked={draft.is_default} onChange={(e) => set("is_default", e.target.checked)} className="size-4 accent-primary" />
        {t("makeDefault")}
      </label>
      {errors.form && (
        <p role="alert" className="text-sm text-destructive">
          {errors.form}
        </p>
      )}
      <div className="flex gap-3">
        <button type="submit" disabled={busy} className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {busy && <Loader2 className="size-4 animate-spin" />}
          {busy ? t("saving") : t("save")}
        </button>
        <button type="button" onClick={onCancel} className={cn(buttonVariants({ variant: "outline" }), "h-11 rounded-full px-6")}>
          {t("cancel")}
        </button>
      </div>
    </form>
  );
}
