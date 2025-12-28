export interface Currency {
  code: string;
  name: string;
  symbol: string;
  decimal_places: number;
  is_default: boolean;
}

export type AccountType = 'bank' | 'credit_card' | 'investment' | 'cash' | 'loan' | 'other';

export interface Account {
  id: number;
  user_id: number;
  name: string;
  account_type: AccountType;
  currency_code: string;
  currency?: Currency;
  balance: number;
  initial_balance: number;
  description?: string;
  icon?: string;
  color?: string;
  is_active: boolean;
  exclude_from_total: boolean;
  created_at: string;
  updated_at: string;
}

export type TransactionType = 'income' | 'expense' | 'transfer';

export interface Category {
  id: number;
  user_id?: number;
  parent_id?: number;
  name: string;
  type: 'income' | 'expense';
  icon?: string;
  color?: string;
  is_active: boolean;
  _lft?: number;
  _rgt?: number;
  children?: Category[];
  created_at: string;
  updated_at: string;
}

export interface Transaction {
  id: number;
  user_id: number;
  account_id: number;
  account?: Account;
  category_id?: number;
  category?: Category;
  type: TransactionType;
  amount: number;
  currency_code: string;
  currency?: Currency;
  description?: string;
  notes?: string;
  transaction_date: string;
  is_reconciled: boolean;
  transfer_account_id?: number;
  transfer_account?: Account;
  transfer_transaction_id?: number;
  created_at: string;
  updated_at: string;
}

export type BudgetPeriod = 'weekly' | 'monthly' | 'quarterly' | 'yearly' | 'custom';

export interface Budget {
  id: number;
  user_id: number;
  category_id?: number;
  category?: Category;
  name: string;
  amount: number;
  currency_code: string;
  currency?: Currency;
  spent: number;
  period_type: BudgetPeriod;
  start_date: string;
  end_date: string;
  is_active: boolean;
  rollover: boolean;
  created_at: string;
  updated_at: string;
}

export interface AccountSummary {
  total_balance: number;
  total_assets: number;
  total_liabilities: number;
  net_worth: number;
  currency_code: string;
  accounts_count: number;
}

export interface FinanceDashboardData {
  summary: AccountSummary;
  recentTransactions: Transaction[];
  budgets: Budget[];
  spendingTrend: SpendingTrendItem[];
}

export interface SpendingTrendItem {
  date: string;
  amount: number;
}

export interface TransactionFilters {
  account_id?: number;
  category_id?: number;
  type?: TransactionType;
  date_from?: string;
  date_to?: string;
  search?: string;
}

export interface BudgetWithProgress extends Budget {
  spent_percent: number;
  remaining: number;
  is_over_budget: boolean;
}

export interface PaginatedData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
