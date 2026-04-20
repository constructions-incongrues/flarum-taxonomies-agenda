export interface AgendaEventAttributes {
  title: string;
  event_date: string;
  date_display: { jour: number; mois: number; annee: number };
  ville: string | null;
  lieu: string | null;
  artistes: string[];
  image_url: string | null;
  excerpt: string | null;
  discussion_url: string;
  user_id: number | null;
}

export interface AgendaEvent {
  type: 'agenda-events';
  id: string;
  attributes: AgendaEventAttributes;
}

export interface AgendaEventsResponse {
  data: AgendaEvent[];
  meta: {
    total: number;
    from: string | null;
    to: string | null;
    limit: number;
    offset: number;
  };
}
