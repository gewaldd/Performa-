"""
Performa ML Engine - RF-owned training catalog.

"RF picks, Gemini explains": the Random Forest pipeline (predict.py) maps
each weak (competency x RF class) pair to one catalog entry below, so the
*choice* of intervention is learned/deterministic, never LLM-authored.
Gemini's only job downstream (gemini_recommender.py) is the human-readable
rationale + summary text. training_type values stay inside
config.GEMINI_TRAINING_TYPES so the review-page edit contract is unchanged.

Stdlib only — importable and testable on any interpreter.
Manuscript: training types P.121; RF classes P.121/P.272.
"""

from __future__ import annotations

import json
import sys
from typing import Dict, List, Optional

try:
    from config import (
        CLASS_CRITICAL_GAP,
        CLASS_NEEDS_IMPROVEMENT,
        COMPETENCY_DISPLAY_NAMES,
        GEMINI_TRAINING_TYPES,
    )
except ImportError:  # pragma: no cover - alternate import root (see predict.py)
    from ml.config import (
        CLASS_CRITICAL_GAP,
        CLASS_NEEDS_IMPROVEMENT,
        COMPETENCY_DISPLAY_NAMES,
        GEMINI_TRAINING_TYPES,
    )

# (competency, rf_class) -> intervention pick. Descriptions are written
# generically across retail / BPO / food service / logistics / construction;
# the employer personalizes them at review time (review page edit flow).
CATALOG: Dict[tuple, dict] = {
    ("task_completion", CLASS_NEEDS_IMPROVEMENT): {
        "training_type": "on-the-job coaching",
        "title": "Daily Task Planning Coaching",
        "description": (
            "Supervisor helps the employee break weekly targets into daily "
            "checklists with a brief end-of-shift review, until output is "
            "consistently on plan."
        ),
        "timeline": "2-4 weeks",
    },
    ("task_completion", CLASS_CRITICAL_GAP): {
        "training_type": "mentoring",
        "title": "Performance Recovery Mentoring",
        "description": (
            "Pair the employee with a senior mentor for closely supervised "
            "task execution with same-day feedback, plus a written recovery "
            "plan with weekly milestones."
        ),
        "timeline": "4-6 weeks",
    },
    ("attendance_punctuality", CLASS_NEEDS_IMPROVEMENT): {
        "training_type": "on-the-job coaching",
        "title": "Attendance & Schedule Discipline Coaching",
        "description": (
            "Agree an explicit shift-start routine and a punctuality log "
            "reviewed weekly with the supervisor, addressing schedule "
            "blockers as they surface."
        ),
        "timeline": "2-4 weeks",
    },
    ("attendance_punctuality", CLASS_CRITICAL_GAP): {
        "training_type": "workshop",
        "title": "Workplace Reliability Workshop",
        "description": (
            "Group workshop on reliability, leave policy and shift-swap "
            "procedure, followed by a signed attendance agreement with "
            "weekly compliance checks."
        ),
        "timeline": "1-2 weeks",
    },
    ("communication_teamwork", CLASS_NEEDS_IMPROVEMENT): {
        "training_type": "mentoring",
        "title": "Buddy Mentoring for Team Communication",
        "description": (
            "Buddy system with a strong communicator: joint handling of "
            "customer interactions with debriefs, plus one team huddle "
            "contribution per week."
        ),
        "timeline": "3-4 weeks",
    },
    ("communication_teamwork", CLASS_CRITICAL_GAP): {
        "training_type": "workshop",
        "title": "Workplace Communication Workshop",
        "description": (
            "Facilitated workshop on active listening, clear handovers and "
            "de-escalation scripts, with role-play assessment before "
            "returning to solo shifts."
        ),
        "timeline": "1-2 weeks",
    },
    ("initiative_adaptability", CLASS_NEEDS_IMPROVEMENT): {
        "training_type": "self-directed learning",
        "title": "Guided Self-Learning: Initiative at Work",
        "description": (
            "Curated short modules on problem ownership with one applied "
            "task per week chosen by the employee and reviewed with the "
            "supervisor."
        ),
        "timeline": "3-4 weeks",
    },
    ("initiative_adaptability", CLASS_CRITICAL_GAP): {
        "training_type": "on-the-job coaching",
        "title": "Stretch-Assignment Coaching",
        "description": (
            "Supervisor assigns one owned stretch task per week outside the "
            "routine, with kickoff guidance and a Friday review, to rebuild "
            "independent problem-solving."
        ),
        "timeline": "4-6 weeks",
    },
    ("quality_of_work", CLASS_NEEDS_IMPROVEMENT): {
        "training_type": "on-the-job coaching",
        "title": "Quality Standards Coaching",
        "description": (
            "Walk through the quality checklist side by side on live work, "
            "then spot-check two outputs per shift against the standard "
            "until errors stay below tolerance."
        ),
        "timeline": "2-4 weeks",
    },
    ("quality_of_work", CLASS_CRITICAL_GAP): {
        "training_type": "workshop",
        "title": "Quality Excellence Workshop",
        "description": (
            "Hands-on workshop on the full quality standard with graded "
            "practice pieces; solo work resumes only after passing the "
            "exit assessment."
        ),
        "timeline": "2-3 weeks",
    },
}

_ALLOWED_TYPES = set(GEMINI_TRAINING_TYPES)

for _key, _entry in CATALOG.items():
    assert _entry["training_type"] in _ALLOWED_TYPES, _key
    assert _entry["title"] and _entry["description"] and _entry["timeline"], _key


def pick_training(competency: str, rf_class: str) -> Optional[dict]:
    """One catalog pick for a (competency, RF class) pair, or None when the
    class is not a weak one (meets_expectations needs no intervention)."""
    entry = CATALOG.get((str(competency), str(rf_class)))
    if entry is None:
        return None
    return {
        "competency_area": str(competency),
        "competency_label": COMPETENCY_DISPLAY_NAMES.get(
            str(competency), str(competency)),
        "rf_class": str(rf_class),
        "training_type": entry["training_type"],
        "title": entry["title"],
        "description": entry["description"],
        "timeline": entry["timeline"],
    }


def rf_recommendations(weak_categories: list,
                       labels_by_competency: dict) -> List[dict]:
    """RF-owned picks for every weak competency, in weak_categories order."""
    picks = []
    for competency in weak_categories or []:
        pick = pick_training(
            competency, (labels_by_competency or {}).get(competency, ""))
        if pick is not None:
            picks.append(pick)
    return picks


def _dump() -> str:
    return json.dumps([
        {"competency": comp, "rf_class": cls, **pick_training(comp, cls)}
        for (comp, cls) in sorted(CATALOG.keys())
    ], indent=2)


if __name__ == "__main__":
    if "--dump" in sys.argv:
        print(_dump())
    elif "--pick" in sys.argv:
        i = sys.argv.index("--pick")
        comp = sys.argv[i + 1] if len(sys.argv) > i + 1 else ""
        cls = sys.argv[i + 2] if len(sys.argv) > i + 2 else ""
        print(json.dumps(pick_training(comp, cls)))
    else:
        print(_dump())
