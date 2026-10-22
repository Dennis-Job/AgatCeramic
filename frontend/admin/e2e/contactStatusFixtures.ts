export const contactStatuses = [
  {
    code: 'new',
    name: 'Новое',
    is_terminal: false,
    allowed_transitions: ['processing', 'rejected'],
  },
  {
    code: 'processing',
    name: 'В обработке',
    is_terminal: false,
    allowed_transitions: ['awaiting_client', 'completed', 'rejected'],
  },
  {
    code: 'awaiting_client',
    name: 'Ожидает клиента',
    is_terminal: false,
    allowed_transitions: ['processing', 'completed', 'rejected'],
  },
  {
    code: 'completed',
    name: 'Завершено',
    is_terminal: true,
    allowed_transitions: [],
  },
  {
    code: 'rejected',
    name: 'Отклонено',
    is_terminal: true,
    allowed_transitions: [],
  },
]
