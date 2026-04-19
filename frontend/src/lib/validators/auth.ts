import { z } from 'zod';

export const passwordSchema = z
  .string()
  .min(8)
  .regex(/[A-Z]/)
  .regex(/[a-z]/)
  .regex(/[0-9]/);

export const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

export const registerSchema = z.object({
  name: z.string().min(1, 'auth.errors.nameRequired').max(255),
  email: z.string().email(),
  password: passwordSchema,
  locale: z.enum(['en', 'es', 'it']).optional(),
});

export const forgotPasswordSchema = z.object({
  email: z.string().email(),
});

export const resetPasswordSchema = z.object({
  email: z.string().email(),
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
