# Multi-Tenancy Strategy: Kintai

🌐 **English** · [Français](multi-tenancy.fr.md) · [日本語](multi-tenancy.ja.md)

## 🛡 Principles
Kintai's multi-tenancy is built on the principle of **Physical Isolation**. We prioritize security and data portability over the density of shared infrastructure.

## 🧱 The "Tenant" Definition
In Kintai, a **Tenant** is defined as an **Owner** (an individual or a company).
- One Tenant = One Application Instance.
- One Tenant = One Database.
- One Tenant = Multiple Stores.

## 🚀 Deployment Model
The Kintai SaaS acts as an orchestrator that automates the deployment of these single-tenant instances.

### 1. Shared Database Server (Optional)
While each tenant has a logical database isolation, they may share a physical database server (e.g., a large MySQL cluster) where each tenant has their own schema/database name.

### 2. Physical Container Isolation
Each tenant runs in its own Docker container, ensuring that resource usage and security vulnerabilities are contained within a single instance.

## 🔄 Lifecycle Management
- **Provisioning:** Automated setup of DB, `.env`, and initial Admin user.
- **Updates:** Managed via the Orchestrator. Tenants can opt-in to different channels (Stable vs. Beta).
- **Backups:** Every tenant database is backed up independently, allowing for granular restoration.

## 📈 Cross-Store Logic
Since one Owner manages all their stores within a single database, multi-store operations are simple:
- **Global User Management:** A user belongs to the Tenant and is assigned roles per Store.
- **Centralized Reporting:** Comparing Labor Costs or Sales across stores is a native SQL query within the tenant's DB.
- **Staff Sharing:** Employees can easily be scheduled across multiple stores owned by the same tenant.

## 🚪 Freedom of Exit
This model is the foundation of our data portability:
- To migrate to self-hosting, the tenant simply needs their database dump and the Kintai source code.
- No "extraction" script is needed to separate their data from other users.
