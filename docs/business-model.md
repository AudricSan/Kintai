# Business Model: Kintai

🌐 **English** · [Français](business-model.fr.md) · [日本語](business-model.ja.md)

## 💰 Monetization Strategy: Open Core, Paid Service

Kintai's entire codebase — core and bundles (Messaging, Daily Reports, and anything added later) — is AGPL-3.0. Nothing in this repository is proprietary or gated behind a paid license: anyone can self-host the full feature set for free. Revenue comes from **operating** Kintai for customers who would rather not, not from withholding features.

### 1. Managed SaaS (main revenue stream)
Kintai as a **Managed SaaS**: same open-source code, run and maintained on the customer's behalf.
- Automated deployment and provisioning (`scripts/provision.php`)
- Managed backups and security updates
- SSL/domain management
- Choice of update channel (Stable / Beta)

### 2. Support & implementation services
- Onboarding and data migration from spreadsheets or legacy tools
- Custom configuration (store rules, payroll deduction settings, translations)
- Priority support contracts for self-hosted Enterprise customers

### 3. Tiered SaaS pricing
Pricing is about hosting capacity and support level, not feature access — every tier runs the same open-source code:
- **Free:** basic scheduling for a single store
- **Pro:** higher limits, affordable for growing teams
- **Business:** multiple stores, priority support
- **Enterprise:** custom limits, dedicated support, custom orchestration

## 🤝 Competitive Advantage
- **Cost efficiency:** a lightweight PHP stack keeps hosting costs — and prices — low.
- **Hybrid flexibility:** the same customer can start on the managed SaaS and move to self-hosting later, or the reverse, without a rewrite.
- **Freedom of exit:** because the code is AGPL and each tenant has its own database, we keep customers with service quality, not data lock-in.

## 📈 Growth Strategy
- **Inbound:** open-source visibility on GitHub, technical content, and the public demo.
- **Direct sales:** targeted outreach to Japanese franchises and store networks looking to move off spreadsheets.
- **Partner ecosystem:** third parties can offer "Kintai implementation" services on top of the same open core.
