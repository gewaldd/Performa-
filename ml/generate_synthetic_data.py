#!/usr/bin/env python3
"""
Generate synthetic training data for Performa Random Forest testing.

ALL DATA IS SYNTHETIC — for testing the ML pipeline only.
"""

import json
import random
import sys
from collections import Counter
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any, Dict, List

random.seed(42)

NUM_EMPLOYEES: int = 50
MONTHS_OF_DATA: int = 6
INDUSTRIES: List[str] = ["retail", "bpo", "food_service", "logistics", "construction"]

INDUSTRY_KPI_TARGETS: Dict[str, Dict[str, float]] = {
    "retail": {"sales_target": 4.0, "customer_service": 4.2, "inventory_accuracy": 4.0, "attendance": 4.5},
    "bpo": {"call_quality": 4.2, "aht": 4.0, "customer_satisfaction": 4.3, "attendance": 4.5},
    "food_service": {"food_safety": 4.5, "service_speed": 4.0, "customer_service": 4.2, "attendance": 4.5},
    "logistics": {"delivery_accuracy": 4.3, "on_time_rate": 4.2, "safety_compliance": 4.5, "attendance": 4.5},
    "construction": {"safety_compliance": 4.5, "work_quality": 4.2, "productivity": 4.0, "attendance": 4.5},
}

KPI_TO_COMPETENCY: Dict[str, List[str]] = {
    "sales_target": ["task_completion"],
    "customer_service": ["communication_teamwork", "initiative_adaptability"],
    "inventory_accuracy": ["quality_of_work"],
    "attendance": ["attendance_punctuality"],
    "call_quality": ["quality_of_work", "communication_teamwork"],
    "aht": ["task_completion"],
    "customer_satisfaction": ["communication_teamwork"],
    "food_safety": ["quality_of_work"],
    "service_speed": ["task_completion"],
    "delivery_accuracy": ["quality_of_work"],
    "on_time_rate": ["task_completion", "attendance_punctuality"],
    "safety_compliance": ["quality_of_work"],
    "work_quality": ["quality_of_work"],
    "productivity": ["task_completion"],
}

COMPETENCY_CATEGORIES = [
    "task_completion",
    "attendance_punctuality",
    "communication_teamwork",
    "initiative_adaptability",
    "quality_of_work",
]

def generate_employee_profile(employee_id: int) -> Dict[str, Any]:
    """Generate a synthetic employee profile."""
    industry = random.choice(INDUSTRIES)
    perf_type = random.choices(
        ["strong", "average", "struggling", "improving", "declining"],
        weights=[0.2, 0.4, 0.15, 0.15, 0.1],
    )[0]
    return {
        "employee_id": f"emp-{employee_id:03d}",
        "industry": industry,
        "performance_type": perf_type,
        "name": f"Employee {employee_id}",
    }


def generate_kpi_scores_for_month(profile: Dict[str, Any], month: int) -> Dict[str, float]:
    """Generate synthetic KPI scores for one employee in one month."""
    industry = profile["industry"]
    perf_type = profile["performance_type"]
    kpi_targets = INDUSTRY_KPI_TARGETS[industry]
    
    base_multipliers = {"strong": 0.95, "average": 0.85, "struggling": 0.70, "improving": 0.75, "declining": 0.90}
    base = base_multipliers[perf_type]
    
    if perf_type == "improving":
        trend = 0.03 * month
    elif perf_type == "declining":
        trend = -0.04 * month
    else:
        trend = 0
    
    scores = {}
    for kpi_key, target in kpi_targets.items():
        base_score = target * (base + trend)
        noise = random.gauss(0, 0.3)
        score = max(0.5, min(5.0, base_score + noise))
        scores[kpi_key] = round(score, 1)
    
    return scores


def scores_to_competency_scores(kpi_scores: Dict[str, float], industry: str) -> Dict[str, float]:
    """Convert KPI scores to competency category scores."""
    competency_scores: Dict[str, List[float]] = {c: [] for c in COMPETENCY_CATEGORIES}
    
    for kpi_key, score in kpi_scores.items():
        if kpi_key in KPI_TO_COMPETENCY:
            for competency in KPI_TO_COMPETENCY[kpi_key]:
                competency_scores[competency].append(score)
    
    result = {}
    for competency, scores in competency_scores.items():
        if scores:
            result[competency] = round(sum(scores) / len(scores), 2)
        else:
            result[competency] = round(random.uniform(3.0, 4.5), 2)
    
    return result


def classify_competency_score(score: float, target: float) -> str:
    """Classify a competency score into one of three classes."""
    if score >= target:
        return "meets_expectations"
    elif score >= target - 0.8:
        return "needs_improvement"
    else:
        return "critical_gap"

def generate_training_records(
    num_employees: int = NUM_EMPLOYEES,
    months: int = MONTHS_OF_DATA,
) -> List[Dict[str, Any]]:
    """Generate synthetic training records for the Random Forest."""
    training_data: List[Dict[str, Any]] = []
    
    for emp_id in range(1, num_employees + 1):
        profile = generate_employee_profile(emp_id)
        industry = profile["industry"]
        kpi_targets = INDUSTRY_KPI_TARGETS[industry]
        
        for month in range(months):
            kpi_scores = generate_kpi_scores_for_month(profile, month)
            comp_scores = scores_to_competency_scores(kpi_scores, industry)
            
            for competency in COMPETENCY_CATEGORIES:
                score = comp_scores[competency]
                
                contributing_kpis = [k for k, comps in KPI_TO_COMPETENCY.items() if competency in comps]
                if contributing_kpis:
                    target_kpis = [kpi_targets.get(k, 4.0) for k in contributing_kpis]
                    target = sum(target_kpis) / len(target_kpis)
                else:
                    target = 4.0
                
                classification = classify_competency_score(score, target)
                
                record = {
                    "employee_id": profile["employee_id"],
                    "evaluation_month": f"2026-{month + 1:02d}",
                    "competency_category": competency,
                    "task_completion": comp_scores.get("task_completion"),
                    "attendance_punctuality": comp_scores.get("attendance_punctuality"),
                    "communication_teamwork": comp_scores.get("communication_teamwork"),
                    "initiative_adaptability": comp_scores.get("initiative_adaptability"),
                    "quality_of_work": comp_scores.get("quality_of_work"),
                    "target_class": classification,
                    "_synthetic": True,
                }
                training_data.append(record)
    
    return training_data
