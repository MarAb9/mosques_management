-- Migration 001: unique protection for mosque national code
-- Status: APPLIED to development DB (Phase 1.1, see docs/CHANGELOG.md)
-- Prerequisite: duplicate/placeholder national_code values must be resolved first.

ALTER TABLE `mosques`
  ADD UNIQUE KEY `uq_mosques_national_code` (`national_code`);
