const MONTHS_FR = [
  '',
  'Janvier',
  'Février',
  'Mars',
  'Avril',
  'Mai',
  'Juin',
  'Juillet',
  'Août',
  'Septembre',
  'Octobre',
  'Novembre',
  'Décembre',
];

const MONTHS_FR_SHORT = [
  '',
  'Jan',
  'Fév',
  'Mar',
  'Avr',
  'Mai',
  'Juin',
  'Juil',
  'Août',
  'Sep',
  'Oct',
  'Nov',
  'Déc',
];

export function monthLabel(m: number): string {
  return MONTHS_FR[m] ?? '';
}

export function monthShort(m: number): string {
  return MONTHS_FR_SHORT[m] ?? '';
}
