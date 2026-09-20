"""
Performa ML Engine - Regularization Decision Support
Location: ml/regularization.py

Manuscript alignment (P.119, P.185, P.591):
- Trigger at middle of 5th month (~day 150) before 180-day Article 296 deadline.
- Synthesizes cumulative monthly RF outputs into an evidence-based draft:
  recommend_regularization / extend_with_plan / recommend_termination_review
- Decision-support ONLY; final decision remains with the employer.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Dict, List


def summarize_monthly_history(monthly_results: List[dict]) -> dict:
    """Aggregate a list of predict_competencies() outputs (chronological)."""
    if not monthly_results:
        raise ValueError("monthly_results is empty")

    n = len(monthly_results)
    overall_seq = [m.get("overall_prediction", {}).get("classification", "") for m in monthly_results]
    weak_counts: Dict[str, int] = {}
    score_sums: Dict[str, float] = {}
    score_ns: Dict[str, int] = {}
    for m in monthly_results:
        for cat, det in (m.get("competency_classification") or {}).items():
            if det.get("classification") in ("needs_improvement", "critical_gap"):
                weak_counts[cat] = weak_counts.get(cat, 0) + 1
            try:
                score_sums[cat] = score_sums.get(cat, 0.0) + float(det.get("score", 0.0))
                score_ns[cat] = score_ns.get(cat, 0) + 1
            except (TypeError, ValueError):
                continue
    avg_scores = {c: round(score_sums[c] / score_ns[c], 2) for c in score_sums if score_ns.get(c)}
    persistent_gaps = sorted([c for c, k in weak_counts.items() if k >= max(2, (n + 1) // 2)])
    n_critical_months = sum(1 for v in overall_seq if v == "critical_gap")
    n_meets_months = sum(1 for v in overall_seq if v == "meets_expectations")
    return {
        "months_evaluated": n,
        "overall_sequence": overall_seq,
        "latest_overall": overall_seq[-1] if overall_seq else "",
        "avg_scores": avg_scores,
        "weak_frequency": weak_counts,
        "persistent_gaps": persistent_gaps,
        "n_critical_months": n_critical_months,
        "n_meets_months": n_meets_months,
    }


def recommend_regularization(
    employee_uid: str,
    monthly_results: List[dict],
    days_employed: int | None = None,
) -> dict:
    """Produce a regularization draft from 1-5 months of RF outputs."""
    summary = summarize_monthly_history(monthly_results)
    latest = summary["latest_overall"]
    n = summary["months_evaluated"]

    if latest == "meets_expectations" and summary["n_critical_months"] == 0:
        rec, conf, rationale = (
            "recommend_regularization", 0.85,
            f"{summary['n_meets_months']}/{n} months Meets Expectations with no Critical Gap months."
        )
    elif summary["n_critical_months"] >= 2 or len(summary["persistent_gaps"]) >= 2:
        rec, conf, rationale = (
            "recommend_termination_review", 0.80,
            f"Persistent gaps in {summary['persistent_gaps'] or 'multiple competencies'} "
            f"across {summary['n_critical_months']} Critical Gap month(s)."
        )
    else:
        rec, conf, rationale = (
            "extend_with_plan", 0.70,
            "Mixed trajectory; targeted training plan advised before the 180-day deadline."
        )

    timing_note = ""
    if days_employed is not None:
        if days_employed < 140:
            timing_note = f"Early draft at day {days_employed}; reconfirm at mid-5th month (~day 150)."
        elif days_employed <= 165:
            timing_note = f"On-time draft at day {days_employed} (mid-5th month window)."
        else:
            timing_note = f"Late draft at day {days_employed}; finalize before day 180 per Article 296."

    return {
        "employee_uid": employee_uid,
        "recommendation": rec,
        "confidence": conf,
        "rationale": rationale,
        "timing_note": timing_note,
        "evidence": summary,
        "model_versions": sorted({m.get("model_version", "unknown") for m in monthly_results}),
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "disclaimer": "Decision-support output only; does not constitute legal advice. "
                      "Final regularization decision remains with the employer.",
    }


if __name__ == "__main__":
    import json
    try:
        from predict import predict_competencies
    except ImportError:
        from ml.predict import predict_competencies
    base = {
        "task_completion": 3.8, "attendance_punctuality": 4.2,
        "communication_teamwork": 3.9, "initiative_adaptability": 3.7,
        "quality_of_work": 3.9,
    }
    months = [predict_competencies("EMP-1", f"2026-0{m}", base) for m in (5, 6, 7, 8, 9)]
    print(json.dumps(recommend_regularization("EMP-1", months, days_employed=150), indent=2))
