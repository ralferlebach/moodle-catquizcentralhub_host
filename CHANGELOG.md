# Changelog – catquizcentralhub_host

## 2026102800 – local_catquiz #65, Re-Audit 27.10.2026

- `hub_disabled_test::test_scheduled_recalculation_is_a_noop`: Der geplante Task `recalculate_remote_item_parameters`
  ist bei ausgeschaltetem Hub ein erfolgreicher No-op, auch wenn eine Skala freigegeben ist. Zahntest ohne die Prüfung:
  rot.
- Am Code ändert sich nichts.
