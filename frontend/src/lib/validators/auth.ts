import { z } from 'zod';

export const passwordSchema = z
  .string()
  .min(8, { message: "auth.errors.weakPassword" })
  .regex(/[A-Z]/, { message: "auth.errors.weakPassword" })
  .regex(/[a-z]/, { message: "auth.errors.weakPassword" })
  .regex(/[0-9]/, { message: "auth.errors.weakPassword" });

export const loginSchema = z.object({
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
  password: z.string().min(1, { message: "auth.errors.invalidCredentials" }),
});

export const registerSchema = z.object({
  name: z.string().min(1, 'auth.errors.nameRequired').max(255),
  email: z.string().email({ message: "auth.errors.invalidCredentials" }),
  password: passwordSchema,
  locale: z.enum(['en', 'es', 'it'], { 
    required_error: "auth.errors.invalidLocale", 
    invalid_type_error: "auth.errors.invalidLocale" 
  }).optional(),
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
