import apiFetch from '@wordpress/api-fetch';
import { safeParseJson } from '@shared/lib/parsing';
import { create } from 'zustand';
import { devtools, persist, createJSONStorage } from 'zustand/middleware';

const path = '/extendify/v1/shared/activity';
const storage = {
	getItem: () => apiFetch({ path }),
	setItem: (_name, state) =>
		apiFetch({ path, method: 'POST', data: { state } }),
};

const incomingState = safeParseJson(window.extSharedData.activity);

const initialState = {
	actions: {},
};

const state = (set, get) => ({
	...initialState,
	...(incomingState?.state ?? {}),
	incrementActivity: (id) => {
		set((state) => ({
			...state,
			actions: {
				...state.actions,
				[id]: Number(get().actions[id] || 0) + 1,
			},
		}));
	},
});

export const useActivityStore = create(
	persist(devtools(state, { name: 'Extendify Activity' }), {
		name: 'extendify_shared_activity',
		storage: createJSONStorage(() => storage),
		skipHydration: true,
	}),
);
