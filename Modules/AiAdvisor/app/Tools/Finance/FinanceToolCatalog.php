<?php

namespace Modules\AiAdvisor\Tools\Finance;

class FinanceToolCatalog
{
    /** @return array<int, class-string<AbstractFinanceTool>> */
    public static function classes(): array
    {
        return [
            GetFinancialSummaryTool::class,
            GetTransactionsTool::class,
            SearchTransactionsTool::class,
            CreateTransactionTool::class,
            UpdateTransactionTool::class,
            CategorizeTransactionTool::class,
            GetCategoriesTool::class,
            GetBudgetsTool::class,
            CreateBudgetTool::class,
            UpdateBudgetTool::class,
            SuggestBudgetTool::class,
            GetGoalsTool::class,
            CreateGoalTool::class,
            SuggestSavingPlanTool::class,
            GetAccountsTool::class,
            GetAccountBalancesTool::class,
            ComparePeriodsTool::class,
            AnalyzeSpendingPatternTool::class,
            ForecastCashFlowTool::class,
            GenerateMonthlyReportTool::class,
            GetCashFlowBreakdownTool::class,
            GetRecurringTransactionsTool::class,
            GetLoansTool::class,
            GetBorrowingsTool::class,
            GetDebtSummaryTool::class,
            CreateTransferTool::class,
            CreateLoanTool::class,
            CreateBorrowingTool::class,
            RecordLoanRepaymentTool::class,
            RecordBorrowingRepaymentTool::class,
            WriteOffLoanTool::class,
            WriteOffBorrowingTool::class,
        ];
    }
}
