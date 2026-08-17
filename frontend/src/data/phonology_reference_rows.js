// Reference rows are not database locations. Add manually curated rows here.
// Paste one exported share code for every mode that the row supports. Each code
// already records its own mode, so their order does not matter. A simplified
// code can also supply the matching features in complete mode.
//
// Example:
// {
//     name: '參考點名稱',
//     color: '#D32913',
//     shareCodes: [
//         'JDP1:……', // exported in efficient mode
//         'JDP1:……', // exported in simplified or complete mode
//     ],
// },
//
// The older `modes: { efficient, simplified, complete }` maps remain supported.
export const PHONOLOGY_REFERENCE_ROWS = [
    {
        name: '普通話',
        color: '#0d1014',
        shareCodes: [
            'JDP1:H4sIAAAAAAACCk2Q3WrCQBSE32Wv44W3gXkajW2KTaSmBRFBIlqjVYsVfxqLBYU2tWpqI1rF9mXcbPIWRXcDXn7MnDNzTp4YuYxCZHKVuzWSasKIZS51TU_rF7mYnjFUXcsSidwpN1lV14gcl8i1njz6lVRKTaiKZhCJZJW0kuBeOU98q4iw2T2ZfXuDwHGIHItL5LApgd5vOdDyO-isz8EfP8Mf1k8jzBuAeVPhGj6BNkd8V20P9lbnAmta8FfeSQhWLsJyW0zM-wjMFwETB7RR4hA-9EF_H0WXrQm29kT8-gv0L7JVLFC3IZTiB_xq-0ypCIUuKmCWuIzOejjsW6LadAHq1gR0XLDOkB_Qc856tjpgy1H0jCrofCwyX38Q7ERMaO8QfE8iWCKcmdE3u_DtT7HAXCO0B0coFP4BMKiePNQBAAA',
            'JDP1:H4sIAAAAAAACCmVW2W5aSRD9lRHPzkNeLZ0vGc1TYs1k5ARr4hkpiiIB5oJZbAhmv9dgG5vVl81sBi48zK_Q21-MnK42A3nr6q6uOlV1qrq_-k6_nBz5Dn1_fvn79P2Hd6dvTv7wf_If-3__8sZ_cvrB_-mz78D3z9Ffnz_4P_kO3x74Pvrfv-i_8388OT46PfId-D4fHR-906qHX3183IXMDH2Hb94e-Hi-AVlztKBqPchVnYRAFZv1mRZk0wXP3GthM3fBSyMS1gWIi6YWmOWAu2kyEApA2TktiFEZPPDwA6BwExBOlQ6mcbBohK5Ev0NFr8ln_jvYckyC9QRlJcnn8zVEokB3zjyIBllj3QJY5JFiW663Bti0DlVc0EnjArxI1mSwD2ZZBGc8hGy1tgKbdciAmwZ37-gkO4UYUHJYfw2eL5HpmQUZ9EitF4PIUXblkweZckymMhANgsPqIch8zxRhiM1i8cuv_xZke_3b_9Tz38lqeALZDJjKXII9eKZmZbDoxJTpFjJJ6GX6EXzZMQZuIR6pzizvQI6nJJSjYF0KUpYuIROkJpfXYM0gBVlMgc_JGn_0oEp9Mh3zICzKhYjEoeywLrqXAy8MtmG9eOskoZ4JO_PuICvkmte6YK_km3cgVzap5SIQ3QE5WDXB8mG6M1yDWXSHJ2Ngw9mOOx5OQeT6u3vBFtgyt5dt-2Z301jvEmVE9QHSKeog1iUIe_hjrUoe5ChFVhoOZMDwvzuAtB4ooFgP6uxyy0UWiFEBHQf81tQ5N4EoGY7EbDCL1MQkCF53tM9VGrwQ1CAHVbAYZUo2MmBpSg6beOBhA6Zd2NKf3yahxjQO2OIOqmbvRC5Dc6jryu7eKAVhn-v1Mgfe72gwNzOIjmlGKwxVp9Egzp4hr6gz5bCiJ8DLQXkNnkvpXHZTECNL70-uIKfGlOdCXBGxeb0LETXzI9SDdOvbE9Z9ppN8B6xKavLMA-s1TC6jYBdPdFKZQ6y0T-6Mwe7Lu2wQTz3wZJs8VByo5dVWkOemJlcOWHdiej0Dnidm83EQvGnu1JuQWZpdorOGjA1MJwfALl9zdw7WrZmpVoQqOJSiIliTtJQ122pt5iHw88AueD4ogKfs3U0Z7EEFarp61ceXFtofNzOweWG3We8H4OucvrSqQUaJ_8kChEsDXLh5sJ7JeauzFfh0DpW82zEpelGwoC69WlR-dtnrQgbjP9ab-T3UWZxcWpCJii5Z4gIiRcOQpSOQVl9j7ETBPZc6vwqeu9uuZ2aapDtQz0QYlqxBJJ41tGwRKkqNqEbrl8drL7NuDKrc3uNK3wO7ONMRRa9fSc7jS6hRU6OP3kJYLb3fsyGT1uta1Jpm9lbBVqZFp0WoG5dQ3gzAZubpiWSgHKK1Crrbl1DYDvjs1mAdQtxdEscDEF57t50nNYj1DSlbXfBpmXAE0mAPutE3ixpUOU5a7R7UzfMeb9wWVCVh7CTAn_rE2ifwu_4eDTsz8GzPvC-z7cPLwhnIgIm-YEGkiWCyfQ8WommliguIsX5f2KIMtqKvh6y3wYs0bbl3D3Zu3pKWDZYxdHGLYBZlnKcvoIIzc9LZNhZvemD9uPmV9MFaZfOryUKu-iaCNlS4bwrz8qkYa2hxG9ydalIsF1DBNeFvXoLXEroEF1moeUGvIy3wp4edEqmGDTa4pFiDkBNCJxpByKXBHSptzTPbhgxF9HW7AnlLY4fnSmD3N3vUzeawmWd_3uSjPZJv5tn9zW_f_gN9NdUYqAoAAA',
        ],
    },
];
