<?php

return [
    'version' => '2.0.0',
    'mode' => env('SENTIMENT_CLASSIFIER_MODE', 'shadow'),
    'model_path' => env('SENTIMENT_MODEL_PATH', base_path('resources/sentiment/model-v1.json')),
    'model_threshold' => (float) env('SENTIMENT_MODEL_THRESHOLD', 0.80),
    'model_version' => 'model-v1.0.0',
    'terms' => [
        'positive' => [
            'en' => [
                'good', 'great', 'excellent', 'amazing', 'wonderful', 'relaxing', 'relaxed', 'relax',
                'clean', 'friendly', 'kind', 'professional', 'comfortable', 'satisfied', 'helpful',
                'accommodating', 'recommend', 'enjoyed', 'love', 'calming',
            ],
            'tl' => [
                'maganda', 'ganda', 'napakaganda', 'mahusay', 'magaling', 'galing', 'mabait', 'bait',
                'malinis', 'linis', 'maayos', 'ayos', 'sulit', 'masarap', 'komportable', 'relaks',
                'nakakarelax', 'nasiyahan', 'nagustuhan', 'gusto', 'alaga', 'babalik', 'irerekomenda',
                'solid',
            ],
        ],
        'negative' => [
            'en' => [
                'bad', 'poor', 'awful', 'terrible', 'worst', 'late', 'slow', 'rude', 'dirty', 'smelly',
                'painful', 'hurt', 'disappointed', 'disappointing', 'unsatisfied', 'uncomfortable',
                'unprofessional', 'overpriced', 'waste', 'complaint', 'complaints',
            ],
            'tl' => [
                'pangit', 'masama', 'marumi', 'dumi', 'mabaho', 'baho', 'masakit', 'sakit', 'bastos',
                'matagal', 'tagal', 'mabagal', 'dismayado', 'bitin', 'ayoko', 'ayaw', 'sayang', 'reklamo',
                'hassle', 'kulang',
            ],
        ],
    ],

    'phrases' => [
        'positive' => [
            'en' => ['worth it', 'highly recommend', 'top notch', 'well taken care of', 'no complaints'],
            'tl' => ['sulit na sulit', 'alaga na alaga', 'walang reklamo', 'babalik ako', 'irerekomenda ko'],
        ],
        'negative' => [
            'en' => ['not worth it', 'waste of money', 'never again', 'not coming back', 'no customer service'],
            'tl' => ['sayang ang pera', 'ayoko na', 'hindi na babalik', 'di na babalik'],
        ],
    ],

    'negations' => [
        'not', 'no', 'never', 'neither', 'nor', 'hardly', 'barely', 'scarcely',
        "isn't", 'isnt', "wasn't", 'wasnt', "weren't", 'werent', "didn't", 'didnt',
        "doesn't", 'doesnt', "don't", 'dont', "can't", 'cant', "couldn't", 'couldnt',
        'hindi', 'di', "'di", 'wala', 'walang',
    ],

    'contrast_words' => [
        'and', 'but', 'however', 'although', 'though', 'yet',
        'at', 'pero', 'ngunit', 'subalit', 'bagaman', 'kahit', 'kaso',
    ],

    'topics' => [
        'care_quality' => [
            'label' => 'Care quality',
            'positive' => ['good', 'great', 'excellent', 'amazing', 'wonderful', 'professional', 'satisfied', 'helpful', 'mahusay', 'magaling', 'maayos', 'nasiyahan', 'serbisyo'],
            'negative' => ['bad', 'poor', 'awful', 'terrible', 'disappointed', 'unsatisfied', 'unprofessional', 'pangit', 'masama', 'dismayado', 'reklamo', 'reklamo'],
        ],
        'therapist_service' => [
            'label' => 'Therapist service',
            'positive' => ['friendly', 'kind', 'accommodating', 'alaga', 'mabait', 'bait', 'care'],
            'negative' => ['rude', 'unprofessional', 'bastos', 'hassle'],
        ],
        'cleanliness_ambience' => [
            'label' => 'Cleanliness and ambience',
            'positive' => ['clean', 'calming', 'comfortable', 'malinis', 'komportable', 'relaks', 'nakakarelax'],
            'negative' => ['dirty', 'smelly', 'uncomfortable', 'marumi', 'mabaho', 'baho'],
        ],
        'scheduling_wait' => [
            'label' => 'Scheduling and wait time',
            'positive' => ['prompt', 'fast', 'mabilis', 'on time', 'on-time'],
            'negative' => ['late', 'slow', 'matagal', 'tagal', 'mabagal', 'hintay', 'waiting', 'wait'],
        ],
        'value_pricing' => [
            'label' => 'Value and pricing',
            'positive' => ['worth it', 'sulit', 'sulit na sulit', 'recommend', 'irerekomenda'],
            'negative' => ['overpriced', 'waste', 'not worth it', 'waste of money', 'sayang', 'kulang'],
        ],
        'pain_comfort' => [
            'label' => 'Pain and comfort',
            'positive' => ['relaxing', 'relaxed', 'relax', 'masarap', 'walang sakit'],
            'negative' => ['painful', 'hurt', 'pain', 'masakit', 'sakit', 'bitin'],
        ],
    ],
];
