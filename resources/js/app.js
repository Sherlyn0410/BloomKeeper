const strengthLevels = [
	{ label: 'Very weak', color: '#dc2626' },
	{ label: 'Weak', color: '#ea580c' },
	{ label: 'Fair', color: '#ca8a04' },
	{ label: 'Strong', color: '#15803d' },
];

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
	const input = toggle.parentElement.querySelector('[data-password-input]');
	const eye = toggle.querySelector('[data-password-eye]');
	const eyeOff = toggle.querySelector('[data-password-eye-off]');

	toggle.addEventListener('click', () => {
		const isVisible = input.type === 'text';
		input.type = isVisible ? 'password' : 'text';
		toggle.dataset.passwordVisible = String(!isVisible);
		toggle.setAttribute('aria-pressed', String(!isVisible));
		toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
		toggle.title = isVisible ? 'Show password' : 'Hide password';
		eye.classList.toggle('hidden', !isVisible);
		eyeOff.classList.toggle('hidden', isVisible);
	});
});

document.querySelectorAll('[data-submit-once]').forEach((form) => {
	form.addEventListener('submit', () => {
		const button = form.querySelector('[data-submit-button]');

		if (!button) {
			return;
		}

		button.disabled = true;
		button.textContent = 'Sending...';
	});
});

document.querySelectorAll('[data-live-filter]').forEach((form) => {

	let timeout;
	const submit = () => form.requestSubmit();

	form.querySelector('[data-live-filter-input]')?.addEventListener('input', () => {
		clearTimeout(timeout);
		timeout = setTimeout(submit, 350);
	});
	form.querySelector('[data-live-filter-select]')?.addEventListener('change', submit);
});

document.querySelectorAll('[data-password-strength-input]').forEach((input) => {
	const container = input.closest('.space-y-2');
	const segments = container.querySelectorAll('[data-password-strength-segment]');
	const label = container.querySelector('[data-password-strength-label]');

	input.addEventListener('input', () => {
		const password = input.value;
		const hasLength = password.length >= 8;
		const hasLongLength = password.length >= 12;
		const hasLowercase = /[a-z]/.test(password);
		const hasUppercase = /[A-Z]/.test(password);
		const hasNumber = /\d/.test(password);
		const hasSymbol = /[^A-Za-z0-9]/.test(password);
		const score = Number(hasLength) + Number(hasLongLength) + Number(hasLowercase) + Number(hasUppercase) + Number(hasNumber) + Number(hasSymbol);
		const level = password.length === 0 ? 0 : Math.min(4, Math.max(1, Math.ceil(score / 1.5)));

		segments.forEach((segment, index) => {
			segment.style.backgroundColor = index < level ? strengthLevels[level - 1].color : '';
		});

		label.textContent = password.length === 0 ? 'Use 8 or more characters.' : strengthLevels[level - 1].label;
		label.style.color = password.length === 0 ? '' : strengthLevels[level - 1].color;
	});
});

const formatPhone = (digits) => {
	if (digits.length <= 3) {
		return digits;
	}

	const rest = digits.slice(3);
	const firstGroupLength = digits.startsWith('011') ? 4 : 3;
	const groups = [rest.slice(0, firstGroupLength), rest.slice(firstGroupLength)];

	return `${digits.slice(0, 3)}-${groups.filter(Boolean).join(' ')}`;
};

const caretAfterDigits = (formatted, digitCount) => {
	let seen = 0;

	for (let index = 0; index < formatted.length; index++) {
		if (seen === digitCount) {
			return index;
		}

		if (/\d/.test(formatted[index])) {
			seen++;
		}
	}

	return formatted.length;
};

document.querySelectorAll('[data-phone-input]').forEach((input) => {
	input.value = formatPhone(input.value.replace(/\D/g, '').slice(0, 11));

	input.addEventListener('keydown', (event) => {
		const position = input.selectionStart;

		if (event.key === 'Backspace' && position === input.selectionEnd && position > 1 && /\D/.test(input.value[position - 1])) {
			event.preventDefault();
			input.value = input.value.slice(0, position - 2) + input.value.slice(position);
			input.setSelectionRange(position - 2, position - 2);
			input.dispatchEvent(new Event('input'));
		}
	});

	input.addEventListener('input', () => {
		const digitsBeforeCaret = input.value.slice(0, input.selectionStart).replace(/\D/g, '').length;
		const formatted = formatPhone(input.value.replace(/\D/g, '').slice(0, 11));
		const caret = caretAfterDigits(formatted, digitsBeforeCaret);

		input.value = formatted;
		input.setSelectionRange(caret, caret);
	});
});
