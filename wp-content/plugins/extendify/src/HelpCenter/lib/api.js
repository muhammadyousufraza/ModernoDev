import { AI_HOST } from '../../constants';

// Additional data to send with requests
const allowList = [
	'siteId',
	'partnerId',
	'wpVersion',
	'wpLanguage',
	'devbuild',
	'isBlockTheme',
	'showAIConsent',
	'userGaveConsent',
	'userId',
];
const extraBody = {
	...Object.fromEntries(
		Object.entries(window.extSharedData).filter(([key]) =>
			allowList.includes(key),
		),
	),
};

export const getAnswer = ({ question, experienceLevel }) =>
	fetch(`${AI_HOST}/api/chat/ask-question`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ question, experienceLevel, ...extraBody }),
	});

export const rateAnswer = ({ answerId, rating }) =>
	fetch(`${AI_HOST}/api/chat/rate-answer`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ answerId, rating }),
	});
