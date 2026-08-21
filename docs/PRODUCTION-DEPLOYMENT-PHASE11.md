# Jaringanku Phase 11 — Production Notes

Phase 11 reuses the Phase 10 production stack and adds ISP Operations tables/routes/pages. No extra external service is required.

Before production rollout:

1. Back up PostgreSQL using the Phase 08 backup workflow.
2. Run migrations in staging.
3. Verify `jaringanku:phase11-preflight`.
4. Verify tenant isolation for tickets, work orders, inventory and network nodes.
5. Populate actual technician records and ODP/ODC coordinates.
6. Restrict admin roles according to your operational policy before public rollout.

GIS-lite does not call external map tiles. If a full Leaflet/OSM base map is added later, review privacy and outbound-network requirements first.
