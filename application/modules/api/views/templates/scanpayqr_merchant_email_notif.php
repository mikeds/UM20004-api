<p>You have successfully created ScanPayQR with the following details:</p>
<table style="border: 1px solid black;border-collapse: collapse; width:50%">
    <tr>
        <th colspan="2">Transaction Details</th>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Ref No.  :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['sender_ref_id']) ? $post['sender_ref_id'] : ""?></td>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Transaction Date and Time  :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['timestamp']) ? $post['timestamp'] : ""?></td>
    </tr>
    <tr>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Amount    :</td>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['amount']) ? number_format($post['amount'], 2, '.', '') : ""?></td>
    </tr>
    <tr>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Fee    :</td>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['fee']) ? number_format($post['fee'], 2, '.', '') : ""?></td>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Total Amount    :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['total_amount']) ? number_format($post['total_amount'], 2, '.', '') : ""?></td>
    </tr>
</table>