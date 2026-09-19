# Performa Feature Extraction
# Transforms weekly KPI ratings into monthly competency scores.
# Does NOT depend on Firestore - pure Python, testable.

from __future__ import annotations
from collections import defaultdict
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple

from config import (
    COMPETENCY_CATEGORIES,
    KPI_COMPETENCY_MAPPING,
    MAX_SCORE,
    MIN_RATINGS_FOR_AGGREGATION,
    MIN_SCORE,
)


CompetencyScores = Dict[str, float]
MonthlyCompetencyData = Dict[Tuple[str, str], CompetencyScores]
RatingDoc = Dict[str, Any]


def _extract_year_month(rated_at: str) -> str:
    """Extract YYYY-MM from ISO8601 timestamp."""
    for fmt in ("%Y-%m-%dT%H:%M:%SZ", "%Y-%m-%dT%H:%M:%S%z", "%Y-%m-%d"):
        try:
            dt = datetime.strptime(rated_at[:len(fmt)], fmt)
            return dt.strftime("%Y-%m")
        except ValueError:
            continue
    try:
        cleaned = rated_at.replace("Z", "+00:00")
        dt = datetime.fromisoformat(cleaned)
        return dt.strftime("%Y-%m")
    except (ValueError, AttributeError):
        pass
    raise ValueError(f"Cannot parse timestamp: {rated_at}")


def get_competency_target(
    industry: str,
    competency: str,
    kpi_targets: Optional[Dict[str, float]] = None,
) -> Optional[float]:
    """Get target score for a competency from KPI targets."""
    if kpi_targets is None:
        kpi_targets = {}
    mapping = KPI_COMPETENCY_MAPPING.get(industry, {})
    targets = []
    for kpi_key, comps in mapping.items():
        if competency in comps:
            targets.append(kpi_targets.get(kpi_key, 4.0))
    if not targets:
        return None
    return sum(targets) / len(targets)


def aggregate_ratings_to_competency_scores(
    ratings: List[RatingDoc],
    employee_uid: str,
    industry: str,
    kpi_targets: Optional[Dict[str, float]] = None,
) -> Optional[CompetencyScores]:
    """Aggregate weekly ratings to monthly competency scores."""
    if not ratings:
        return None
    if kpi_targets is None:
        kpi_targets = {}
    
    mapping = KPI_COMPETENCY_MAPPING.get(industry, {})
    monthly_ratings: Dict[str, List[RatingDoc]] = defaultdict(list)
    
    for r in ratings:
        if r.get("employeeUid") != employee_uid:
            continue
        rated_at = r.get("ratedAt", "")
        if not rated_at:
            continue
        try:
            ym = _extract_year_month(rated_at)
        except ValueError:
            continue
        scores = r.get("scores", {})
        if isinstance(scores, dict):
            monthly_ratings[ym].append(scores)
    
    if not monthly_ratings:
        return None
    
    for month in sorted(monthly_ratings.keys(), reverse=True):
        month_scores = monthly_ratings[month]
        if len(month_scores) < MIN_RATINGS_FOR_AGGREGATION:
            continue
        
        result: CompetencyScores = {}
        for competency in COMPETENCY_CATEGORIES:
            contributing = []
            for kpi_key, comps in mapping.items():
                if competency in comps:
                    for scores_doc in month_scores:
                        if kpi_key in scores_doc:
                            val = float(scores_doc[kpi_key])
                            if MIN_SCORE <= val <= MAX_SCORE:
                                contributing.append(val)
            if contributing:
                result[competency] = sum(contributing) / len(contributing)
            else:
                result[competency] = None
        return result
    
    return None


def extract_all_competency_scores(
    all_ratings: List[RatingDoc],
    industry: str,
    kpi_targets: Optional[Dict[str, float]] = None,
) -> MonthlyCompetencyData:
    """Extract competency scores for all employees."""
    if kpi_targets is None:
        kpi_targets = {}
    
    by_employee: Dict[str, List[RatingDoc]] = defaultdict(list)
    for r in all_ratings:
        uid = r.get("employeeUid", "")
        if uid:
            by_employee[uid].append(r)
    
    result: MonthlyCompetencyData = {}
    for uid, ratings in by_employee.items():
        scores = aggregate_ratings_to_competency_scores(ratings, uid, industry, kpi_targets)
        if scores:
            months = sorted(
                set(_extract_year_month(r.get("ratedAt", "")) for r in ratings if r.get("ratedAt")),
                reverse=True,
            )
            if months:
                result[(uid, months[0])] = scores
    return result


def validate_competency_scores(scores: CompetencyScores) -> List[str]:
    """Validate competency scores."""
    issues = []
    for c in COMPETENCY_CATEGORIES:
        s = scores.get(c)
        if s is None:
            issues.append(f"Missing: {c}")
        elif not (MIN_SCORE <= s <= MAX_SCORE):
            issues.append(f"Invalid {c}: {s}")
    return issues


FEATURE_NAMES: List[str] = list(COMPETENCY_CATEGORIES)

__all__ = [
    "CompetencyScores", "MonthlyCompetencyData", "RatingDoc",
    "aggregate_ratings_to_competency_scores",
    "extract_all_competency_scores",
    "validate_competency_scores",
    "FEATURE_NAMES",
]
