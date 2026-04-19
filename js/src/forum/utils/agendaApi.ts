import app from 'flarum/forum/app';
import type { AgendaEventsResponse } from '../models/AgendaEvent';
import type { AgendaFacetsResponse } from '../models/AgendaFacets';

export interface AgendaFiltersState {
  from?: string;
  to?: string;
  ville?: string;
}

function buildParams(filters: AgendaFiltersState): Record<string, any> {
  const params: Record<string, any> = { 'page[limit]': 200 };
  const filter: Record<string, string> = {};
  if (filters.from) filter.from = filters.from;
  if (filters.to) filter.to = filters.to;
  if (filters.ville) filter.ville = filters.ville;
  if (Object.keys(filter).length) params.filter = filter;
  return params;
}

export function fetchEvents(filters: AgendaFiltersState): Promise<AgendaEventsResponse> {
  return app.request<AgendaEventsResponse>({
    method: 'GET',
    url: app.forum.attribute('apiUrl') + '/agenda/events',
    params: buildParams(filters),
  });
}

export function fetchFacets(): Promise<AgendaFacetsResponse> {
  return app.request<AgendaFacetsResponse>({
    method: 'GET',
    url: app.forum.attribute('apiUrl') + '/agenda/facets',
  });
}
