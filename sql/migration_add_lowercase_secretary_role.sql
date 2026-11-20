-- The application stores normalized lowercase role keys in public.users.
-- Keep the historical capitalized enum value for existing rows while allowing
-- new Secretary accounts to use the same lowercase key as the other roles.
ALTER TYPE public.user_role ADD VALUE IF NOT EXISTS 'secretary';
