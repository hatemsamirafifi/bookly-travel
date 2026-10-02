import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import PasswordChangeForm from '../PasswordChangeForm';

jest.mock('next-intl', () => ({ useTranslations: () => (key: string) => key }));

function fillPasswordForm() {
  fireEvent.change(screen.getByLabelText('currentPassword'), { target: { value: 'oldpassword1' } });
  fireEvent.change(screen.getByLabelText('newPassword'), { target: { value: 'newpassword1' } });
  fireEvent.change(screen.getByLabelText('confirmPassword'), { target: { value: 'newpassword1' } });
}

describe('PasswordChangeForm', () => {
  it('retains fields when the server rejects a password change', async () => {
    const onSubmit = jest.fn().mockRejectedValue(new Error('Rejected'));
    render(<PasswordChangeForm onSubmit={onSubmit} />);
    fillPasswordForm();
    fireEvent.click(screen.getByRole('button', { name: 'updatePassword' }));
    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(screen.getByLabelText('newPassword')).toHaveValue('newpassword1'));
  });

  it('clears fields only after a successful change', async () => {
    const onSubmit = jest.fn().mockResolvedValue(undefined);
    render(<PasswordChangeForm onSubmit={onSubmit} />);
    fillPasswordForm();
    fireEvent.click(screen.getByRole('button', { name: 'updatePassword' }));
    await waitFor(() => expect(screen.getByLabelText('newPassword')).toHaveValue(''));
  });
});
