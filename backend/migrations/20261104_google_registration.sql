-- Preserve imported accounts; require a real birth date for Google onboarding.
ALTER TABLE user_profiles ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER age;
