import { z } from 'zod';

export const passwordSchema = z
  .string()
  .min(8, { message: "auth.errors.weakPassword" })
  .regex(/[A-Z]/, { message: "auth.errors.weakPassword" })
  .regex(/[a-z]/, { message: "auth.errors.weakPassword" })
  .regex(/[0-9]/, { message: "auth.errors.weakPassword" });

export const loginSchema = z.object({
  email: z.string().email({ message: "auth.errors.invalidEmail" }),
  password: z.string().min(1, { message: "auth.errors.passwordRequired" }),
});

export const registerSchema = z.object({
  name: z.string().min(1, 'auth.errors.nameRequired').max(255),
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
  password: passwordSchema,
  locale: z.enum(['en', 'es', 'it']).optional(),
});

export const forgotPasswordSchema = z.object({
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
});

export const resetPasswordSchema = z.object({
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
  token: z.string().min(1),
  password: passwordSchema,
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: "auth.errors.passwordsMismatch",
  path: ["password_confirmation"],
});

export const changePasswordSchema = z.object({
  current_password: z.string().min(1),
  password: passwordSchema,
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: "auth.errors.passwordsMismatch",
  path: ["password_confirmation"],
});

export const guestConvertSchema = z.object({
  name: z.string().min(1, 'auth.errors.nameRequired').max(255),
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
  password: passwordSchema,
  password_confirmation: z.string(),
  booking_reference: z.string().min(1),
}).refine((data) => data.password === data.password_confirmation, {
  message: "auth.errors.passwordsMismatch",
  path: ["password_confirmation"],
});

export const partnerRegisterSchema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  email: z.string().email({ message: "Invalid email address" }),
  password: passwordSchema,
  password_confirmation: z.string(),
  company_name: z.string().min(1, 'Company name is required').max(255),
  contact_email: z.string().email({ message: "Invalid contact email address" }),
  contact_phone: z.string().min(1, 'Contact phone is required').max(50),
  business_description: z.string().min(10, 'Description must be at least 10 characters').max(1000),
  street: z.string().min(1, 'Street address is required').max(255),
  city: z.string().min(1, 'City is required').max(255),
  state: z.string().max(255).optional().or(z.literal('')),
  postal_code: z.string().min(1, 'Postal code is required').max(20),
  country: z.string().length(2, 'Country code must be 2 characters (e.g. US, ES)'),
  tax_id: z.string().max(50).optional().or(z.literal('')),
  payout_country: z.string().length(2, 'Payout country code must be 2 characters'),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords mismatch",
  path: ["password_confirmation"],
});

