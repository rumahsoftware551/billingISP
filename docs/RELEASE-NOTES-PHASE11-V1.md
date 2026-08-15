# Phase 11 V1 Release Notes

Phase 11 turns Jaringanku from billing/network control into a broader ISP operations platform.

Core additions: technician management, support tickets, customer-portal tickets, work orders, installation workflow, inventory, inventory movements, ODP/ODC topology records, service port assignment, and coordinate-based GIS-lite visualization.

Security: route-bound tenant models used by Field Operations are explicitly rechecked against `CurrentTenant`, and portal ticket access is explicitly constrained to the authenticated portal customer.

Runtime status is only considered passed when `scripts/local-up.ps1` completes the Phase 04–10 regression chain plus Phase 11 preflight/smoke and prints `JARINGANKU PHASE 11 SIAP`.
